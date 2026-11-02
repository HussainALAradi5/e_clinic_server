<?php

declare(strict_types=1);

namespace App\Enums\Accounting;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Normal balance side of an account.
 */
enum BalanceSide: string implements HasLabel
{
    use EnumHelpers;

    case Debit = 'debit';
    case Credit = 'credit';
}
