<?php

declare(strict_types=1);

namespace App\Events\Communication;

use App\Models\Communication\SupportTicket;
use Illuminate\Foundation\Events\Dispatchable;

/** A ticket was opened, answered or moved (the listener sends the notifications). */
final class TicketChanged
{
    use Dispatchable;

    /**
     * @param  'opened'|'commented'|'status'|'confirmed'|'declined'  $change
     */
    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly string $change,
        public readonly string $actorId,
    ) {}
}
