<?php

declare(strict_types=1);

namespace App\Modules\Communication\Support\Events;

use App\Modules\Communication\Support\Http\Resources\TicketResource;
use App\Modules\Communication\Support\Models\SupportTicket;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** The live side of a ticket change: to its requester (`user.{id}`) and the support desk (`support`, admins). */
final class TicketBroadcast implements ShouldBroadcastNow
{
    /**
     * @param  'opened'|'commented'|'status'  $change
     */
    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly string $change,
        public readonly string $actorId,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->ticket->requester_id), new PrivateChannel('support')];
    }

    public function broadcastAs(): string
    {
        return 'ticket.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['change' => $this->change, 'ticket' => (new TicketResource($this->ticket->loadMissing(['requester.person', 'assignee.person'])))->resolve()];
    }
}
