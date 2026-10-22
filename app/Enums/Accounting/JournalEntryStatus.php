<?php

declare(strict_types=1);

namespace App\Enums\Accounting;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

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
