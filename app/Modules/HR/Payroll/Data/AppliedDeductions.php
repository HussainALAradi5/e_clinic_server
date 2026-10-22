<?php

declare(strict_types=1);

namespace App\Modules\HR\Payroll\Data;

use App\Enums\HR\PayslipItemType;
use App\Modules\HR\Payroll\Models\PayslipItem;
use App\Shared\Support\MoneyHelper;
use Brick\Money\Money;

/**
 * What a payslip's deductions actually take from its gross pay, in order — absences and unpaid leave, then social
 * insurance, then other deductions — so the net is never negative; the part that does not fit is carried forward.
 * The salary accrual is posted from these amounts (expense = gross − absences = net + insurance + other).
 */
final readonly class AppliedDeductions
{
    public function __construct(
        public Money $gross,
        public Money $absences,
        public Money $socialInsurance,
        public Money $other,
        public Money $net,
        public Money $carriedForward,
    ) {}

    public static function of(Money $gross, Money $absences, Money $socialInsurance, Money $other): self
    {
        $left = $gross;
        $take = function (Money $wanted) use (&$left): Money {
            $taken = $wanted->isGreaterThan($left) ? $left : $wanted;
            $left = $left->minus($taken);

            return $taken;
        };
        $appliedAbsences = $take($absences);
        $appliedInsurance = $take($socialInsurance);
        $appliedOther = $take($other);
        $wanted = $absences->plus($socialInsurance)->plus($other);

        return new self($gross, $appliedAbsences, $appliedInsurance, $appliedOther, $left, $wanted->minus($appliedAbsences)->minus($appliedInsurance)->minus($appliedOther));
    }

    /**
     * Recomputes the applied amounts from stored payslip lines.
     *
     * @param  iterable<PayslipItem>  $items
     */
    public static function fromItems(iterable $items): self
    {
        $sum = ['gross' => MoneyHelper::zero(), 'absences' => MoneyHelper::zero(), 'insurance' => MoneyHelper::zero(), 'other' => MoneyHelper::zero()];
        foreach ($items as $item) {
            $key = match ($item->type) {
                PayslipItemType::Basic, PayslipItemType::Allowance, PayslipItemType::Overtime => 'gross',
                PayslipItemType::Absence, PayslipItemType::UnpaidLeave => 'absences',
                PayslipItemType::SocialInsurance => 'insurance',
                PayslipItemType::Deduction, PayslipItemType::Adjustment => 'other',
            };
            $sum[$key] = $sum[$key]->plus($item->amount);
        }

        return self::of($sum['gross'], $sum['absences'], $sum['insurance'], $sum['other']);
    }

    public function totalDeductions(): Money
    {
        return $this->gross->minus($this->net);
    }

    /** Salary cost booked for the month (gross less absences and unpaid leave). */
    public function expense(): Money
    {
        return $this->gross->minus($this->absences);
    }
}
