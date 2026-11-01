<?php

declare(strict_types=1);

namespace App\Modules\Communication\Support\Listeners;

use App\Enums\Communication\TicketStatus;
use App\Modules\Communication\Support\Events\TicketChanged;
use App\Modules\Identity\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Identity\Users\Models\Person;
use App\Shared\Support\Recipients;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Who hears about a ticket: a new one reaches the support desk (tickets.resolve); every move reaches the requester;
 * a reply reaches the other side — the requester when staff answered, the assignee (or the desk) when the requester did.
 */
final class SendTicketNotifications implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly NotificationServiceInterface $notifications) {}

    public function handle(TicketChanged $event): void
    {
        $ticket = $event->ticket;
        $data = ['code' => $ticket->code, 'subject' => $ticket->subject, 'status' => $ticket->status->label(), 'resolution' => (string) $ticket->resolution];
        $byRequester = $event->actorId === $ticket->requester_id;

        [$template, $people] = match ($event->change) {
            'opened' => ['ticket.opened', Recipients::withPermission('tickets.resolve', [$event->actorId])],
            'status' => [$ticket->status === TicketStatus::Resolved ? 'ticket.resolved' : 'ticket.updated', Recipients::user($ticket->requester_id)],
            'commented' => ['ticket.replied', match (true) {
                ! $byRequester => Recipients::user($ticket->requester_id),
                $ticket->assigned_to !== null => Recipients::user($ticket->assigned_to),
                default => Recipients::withPermission('tickets.resolve', [$event->actorId]),
            }],
        };

        $people->unique('id')->each(fn (Person $p) => $this->notifications->send($template, $p, $ticket, $data));
    }
}
