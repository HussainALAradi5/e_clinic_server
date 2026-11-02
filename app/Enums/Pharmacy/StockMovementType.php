<?php

declare(strict_types=1);

namespace App\Enums\Pharmacy;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Reason of a stock ledger row (signed quantity: + in, − out).
 */
enum StockMovementType: string implements HasLabel
{
    use EnumHelpers;

    case Receipt = 'receipt';
    case Dispense = 'dispense';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case ExpiryWriteoff = 'expiry_writeoff';
    /** Moved between the pharmacy and the treatment room (− on one lot, + on the other). */
    case Transfer = 'transfer';
}
