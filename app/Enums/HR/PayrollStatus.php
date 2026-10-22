<?php

declare(strict_types=1);

namespace App\Enums\HR;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Monthly payroll run: draft → calculated → approved (accrual posted) → paid; cancelled before payment.
 */
enum PayrollStatus: string implements HasLabel
{
    use EnumHelpers;

    case Draft = 'draft';
    case Calculated = 'calculated';
    case Approved = 'approved';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
