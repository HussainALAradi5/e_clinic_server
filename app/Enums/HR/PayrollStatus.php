<?php

declare(strict_types=1);

namespace App\Enums\HR;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

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
