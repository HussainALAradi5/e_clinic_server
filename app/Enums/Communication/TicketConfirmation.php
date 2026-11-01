<?php

declare(strict_types=1);

namespace App\Enums\Communication;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * The requester's answer once a ticket is marked solved: awaiting (asked), confirmed (it works) or declined
 * (still broken — the ticket goes back to in progress with the reason).
 */
enum TicketConfirmation: string implements HasLabel
{
    use EnumHelpers;

    case Awaiting = 'awaiting';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
}
