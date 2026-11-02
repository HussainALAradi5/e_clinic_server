<?php

declare(strict_types=1);

namespace App\Enums\HR;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Payslip line; the amount is always positive, the type says whether it adds to or deducts from the pay.
 */
enum PayslipItemType: string implements HasLabel
{
    use EnumHelpers;

    case Basic = 'basic';
    case Allowance = 'allowance';
    case Overtime = 'overtime';
    case Deduction = 'deduction';
    case Absence = 'absence';
    case Lateness = 'lateness';
    case UnpaidLeave = 'unpaid_leave';
    case SocialInsurance = 'social_insurance';
    case Adjustment = 'adjustment';

    /** Earnings add to gross pay; everything else is deducted. */
    public function isEarning(): bool
    {
        return in_array($this, [self::Basic, self::Allowance, self::Overtime], true);
    }
}
