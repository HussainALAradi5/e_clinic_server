<?php

declare(strict_types=1);

namespace App\Enums\Payments;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Where an invoice stands with its payments, for badges and filters: worked out from the status, the due date and
 * the refunds, never stored. Unpaid past its due date is overdue; any refund on a paid invoice is refunded.
 */
enum InvoicePaymentState: string implements HasLabel
{
    use EnumHelpers;

    case Draft = 'draft';
    case Unpaid = 'unpaid';
    case Overdue = 'overdue';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';
}
