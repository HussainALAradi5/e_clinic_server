<?php

declare(strict_types=1);

namespace App\Enums\Accounting;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Journal entry lifecycle: drafts are editable, posted entries are immutable (reversed by a mirror entry).
 */
enum JournalEntryStatus: string implements HasLabel
{
    use EnumHelpers;

    case Draft = 'draft';
    case Posted = 'posted';
    case Reversed = 'reversed';
}
