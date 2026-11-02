<?php

declare(strict_types=1);

namespace App\Enums\HR;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Why employment ended.
 */
enum TerminationReason: string implements HasLabel
{
    use EnumHelpers;

    case Resignation = 'resignation';
    case EndOfContract = 'end_of_contract';
    case Dismissal = 'dismissal';
    case Retirement = 'retirement';
    case Other = 'other';
}
