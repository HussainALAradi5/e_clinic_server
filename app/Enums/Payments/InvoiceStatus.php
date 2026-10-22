<?php

declare(strict_types=1);

namespace App\Enums\Payments;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Invoice lifecycle (draft collects charges; issued gets a number and posts revenue).
 */
enum InvoiceStatus: string implements HasLabel
{
    use EnumHelpers;

    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /** Issued and not fully paid: payments can be recorded. */
    public function isPayable(): bool
    {
        return in_array($this, [self::Issued, self::PartiallyPaid], true);
    }

    /** Issued, partially paid or paid: carries a number and a revenue journal entry. */
    public function isIssued(): bool
    {
        return in_array($this, [self::Issued, self::PartiallyPaid, self::Paid], true);
    }
}
