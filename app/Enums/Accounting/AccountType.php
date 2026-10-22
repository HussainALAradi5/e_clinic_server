<?php

declare(strict_types=1);

namespace App\Enums\Accounting;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Top-level account classification.
 */
enum AccountType: string implements HasLabel
{
    use EnumHelpers;

    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    /** Normal balance of accounts of this type (asset / expense = debit). */
    public function normalBalance(): BalanceSide
    {
        return in_array($this, [self::Asset, self::Expense], true) ? BalanceSide::Debit : BalanceSide::Credit;
    }
}
