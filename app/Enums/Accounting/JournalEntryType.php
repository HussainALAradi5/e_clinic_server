<?php

declare(strict_types=1);

namespace App\Enums\Accounting;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Origin of a journal entry.
 */
enum JournalEntryType: string implements HasLabel
{
    use EnumHelpers;

    case Manual = 'manual';
    case Automatic = 'automatic';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';
    case Opening = 'opening';
    case Closing = 'closing';
}
