<?php

declare(strict_types=1);

namespace App\Modules\Communication\Support\Services;

use App\Enums\Communication\TicketConfirmation;
use App\Modules\Communication\Support\Contracts\SupportTicketServiceInterface;
use App\Modules\Communication\Support\Data\TicketData;
use App\Modules\Communication\Support\Events\TicketBroadcast;
use App\Modules\Communication\Support\Events\TicketChanged;
use App\Modules\Communication\Support\Exceptions\TicketResolutionRequiredException;
use App\Modules\Communication\Support\Models\SupportTicket;
use App\Modules\Communication\Support\Models\TicketComment;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Access\OwnershipScope;
use App\Shared\Enums\GenericStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Services\BaseEntityService;
use App\Shared\Support\ClinicClock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseEntityService<SupportTicket>
 */
final class SupportTicketService extends BaseEntityService implements SupportTicketServiceInterface
{
    public const RELATIONS = ['requester.person', 'assignee.person', 'resolver.person'];

    public function __construct(ApplyListQuery $listQuery, private readonly ClinicClock $clock)
    {
        parent::__construct($listQuery);
    }

    public function find(string $id): SupportTicket
    {
        return $this->query()->with(['comments.author.person', 'attachments'])->withCount('comments')->findOrFail($id);
    }

    public function open(User $requester, TicketData $data): SupportTicket
    {
        $ticket = DB::transaction(fn (): SupportTicket => SupportTicket::query()->create([
            'requester_id' => $requester->id,
            'subject' => $data->subject,
            'description' => $data->description,
            'category' => $data->category,
            'priority' => $data->priority,
            'status' => GenericStatus::Pending,
        ]));
        $this->announce($ticket, 'opened', $requester);

        return $this->find($ticket->id);
    }

    public function comment(SupportTicket $ticket, User $author, string $body): TicketComment
    {
        if ($ticket->status === GenericStatus::Cancelled) {
            throw new InvalidStateTransitionException(context: ['from' => GenericStatus::Cancelled->value, 'to' => 'commented']);
        }
        $comment = DB::transaction(function () use ($ticket, $author, $body): TicketComment {
            $comment = $ticket->comments()->create(['author_id' => $author->id, 'body' => $body]);
            // A reply never changes the status: the requester confirms or declines a solved ticket explicitly.
            $ticket->touch();

            return $comment;
        });
        $this->announce($ticket, 'commented', $author);

        return $comment->load('author.person');
    }

    /**
     * @param  list<string>  $steps
     */
    public function changeStatus(SupportTicket $ticket, User $admin, GenericStatus $to, ?string $resolution, array $steps, int $version): SupportTicket
    {
        if (! $admin->can('tickets.resolve')) {
            throw new AuthorizationException(__('errors.FORBIDDEN'));
        }

        $ticket = DB::transaction(function () use ($ticket, $admin, $to, $resolution, $steps, $version): SupportTicket {
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $locked->assertVersion($version);
            if (! $locked->canMoveTo($to)) {
                throw new InvalidStateTransitionException(context: ['from' => $locked->status->value, 'to' => $to->value]);
            }
            if ($to === GenericStatus::Completed && blank($resolution)) {
                throw new TicketResolutionRequiredException;
            }
            $locked->status = $to;
            if ($to === GenericStatus::InProgress && $locked->assigned_to === null) {
                $locked->assigned_to = $admin->id;
            }
            if ($to === GenericStatus::Completed) {
                // The solution in words, and the steps when the fix was step by step; the requester is asked to confirm.
                $locked->forceFill([
                    'resolution' => $resolution,
                    'resolution_steps' => $steps === [] ? null : $steps,
                    'resolved_by' => $admin->id,
                    'resolved_at' => $this->clock->now(),
                    'confirmation' => TicketConfirmation::Awaiting,
                    'confirmed_at' => null,
                ]);
            } elseif ($to === GenericStatus::Pending) {
                // Reopened: no longer solved.
                $locked->forceFill(['resolved_by' => null, 'resolved_at' => null, 'confirmation' => null, 'confirmed_at' => null]);
            }
            $locked->save();

            return $locked;
        });
        $this->announce($ticket, 'status', $admin);

        return $this->find($ticket->id);
    }

    public function confirm(SupportTicket $ticket, User $requester): SupportTicket
    {
        $this->assertAwaiting($ticket, $requester);
        $ticket->forceFill(['confirmation' => TicketConfirmation::Confirmed, 'confirmed_at' => $this->clock->now()])->save();
        $this->announce($ticket, 'confirmed', $requester);

        return $this->find($ticket->id);
    }

    public function decline(SupportTicket $ticket, User $requester, string $reason): SupportTicket
    {
        $this->assertAwaiting($ticket, $requester);
        DB::transaction(function () use ($ticket, $requester, $reason): void {
            // Still broken: back to the person who worked on it, with the reason in the conversation.
            $ticket->comments()->create(['author_id' => $requester->id, 'body' => __('notifications.ticket.declined_comment', ['reason' => $reason])]);
            $ticket->forceFill([
                'status' => GenericStatus::InProgress,
                'assigned_to' => $ticket->assigned_to ?? $ticket->resolved_by,
                'confirmation' => TicketConfirmation::Declined,
                'confirmed_at' => $this->clock->now(),
                'resolved_by' => null,
                'resolved_at' => null,
            ])->save();
        });
        $this->announce($ticket, 'declined', $requester);

        return $this->find($ticket->id);
    }

    /** Only the requester answers, and only while the solved ticket waits for it. */
    private function assertAwaiting(SupportTicket $ticket, User $user): void
    {
        if ($ticket->requester_id !== $user->id) {
            throw new AuthorizationException(__('errors.TICKET_REQUESTER_ONLY'));
        }
        if (! $ticket->awaitsConfirmation()) {
            throw new InvalidStateTransitionException(context: ['from' => $ticket->status->value, 'to' => 'confirmed']);
        }
    }

    protected function query(): Builder
    {
        return OwnershipScope::apply(SupportTicket::query()->with(self::RELATIONS)->withCount('comments'), 'tickets', Auth::user());
    }

    /**
     * @param  'opened'|'commented'|'status'|'confirmed'|'declined'  $change
     */
    private function announce(SupportTicket $ticket, string $change, User $actor): void
    {
        $fresh = SupportTicket::query()->with(self::RELATIONS)->findOrFail($ticket->id);
        TicketChanged::dispatch($fresh, $change, $actor->id);
        // The live update must never fail the request (the socket server may be down).
        rescue(fn () => broadcast(new TicketBroadcast($fresh, $change, $actor->id)), report: true);
    }
}
