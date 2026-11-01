<?php

declare(strict_types=1);

namespace App\Enums\Communication;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/** How soon a ticket needs an answer. */
enum TicketPriority: string implements HasLabel
{
    use EnumHelpers;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';
}
