<?php

declare(strict_types=1);

namespace App\Listeners\Communication;

use App\Enums\Shared\GenericStatus;
use App\Events\Communication\TicketChanged;
use App\Interfaces\Identity\NotificationServiceInterface;
use App\Models\Identity\Person;
use App\Utilities\Shared\Recipients;
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
        $data = ['code' => $ticket->code, 'subject' => $ticket->subject, 'status' => mb_strtolower($ticket->status->label()), 'resolution' => $ticket->solutionText()];
        $byRequester = $event->actorId === $ticket->requester_id;

        [$template, $people] = match ($event->change) {
            'opened' => ['ticket.opened', Recipients::withPermission('tickets.resolve', [$event->actorId])],
            'status' => [$ticket->status === GenericStatus::Completed ? 'ticket.resolved' : 'ticket.updated', Recipients::user($ticket->requester_id)],
            // The requester's answer reaches whoever worked on it (or the desk).
            'confirmed', 'declined' => ["ticket.{$event->change}", $ticket->assigned_to !== null
                ? Recipients::user($ticket->assigned_to)
                : Recipients::withPermission('tickets.resolve', [$event->actorId])],
            'commented' => ['ticket.replied', match (true) {
                ! $byRequester => Recipients::user($ticket->requester_id),
                $ticket->assigned_to !== null => Recipients::user($ticket->assigned_to),
                default => Recipients::withPermission('tickets.resolve', [$event->actorId]),
            }],
        };

        $people->unique('id')->each(fn (Person $p) => $this->notifications->send($template, $p, $ticket, $data));
    }
}
