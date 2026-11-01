<?php

declare(strict_types=1);

namespace App\Enums\Communication;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/** What a support ticket is about. */
enum TicketCategory: string implements HasLabel
{
    use EnumHelpers;

    case Technical = 'technical';
    case Access = 'access';
    case Equipment = 'equipment';
    case Data = 'data';
    case Other = 'other';
}
