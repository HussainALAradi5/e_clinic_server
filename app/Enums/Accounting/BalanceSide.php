<?php

declare(strict_types=1);

namespace App\Enums\Accounting;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Normal balance side of an account.
 */
enum BalanceSide: string implements HasLabel
{
    use EnumHelpers;

    case Debit = 'debit';
    case Credit = 'credit';
}
