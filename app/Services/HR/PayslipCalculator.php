<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Dtos\HR\AppliedDeductions;
use App\Dtos\HR\PayslipLine;
use App\Enums\HR\CommissionType;
use App\Enums\HR\PayslipItemType;
use App\Enums\HR\SalaryComponentType;
use App\Enums\HR\SettingKey;
use App\Interfaces\HR\AttendanceServiceInterface;
use App\Interfaces\HR\SettingServiceInterface;
use App\Models\Appointments\Doctor;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeSalaryComponent;
use App\Utilities\HR\AttendancePay;
use App\Utilities\HR\DoctorEarnings;
use App\Utilities\Shared\MoneyHelper;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

/**
 * One employee's month: basic (pro-rated by calendar days for joiners and leavers), allowances, approved overtime,
 * then absences, unexcused late arrivals (when HR uses late excuses), unpaid leave, social insurance (by nationality), other deductions and last month's carried shortfall.
 * Money stays exact (BigDecimal, half-up to fils).
 */
final class PayslipCalculator
{
    public function __construct(
        private readonly AttendanceServiceInterface $attendance,
        private readonly SettingServiceInterface $settings,
        private readonly AttendancePay $pay,
    ) {}

    /**
     * Payslip columns and lines, or null when the employee did not work for the clinic that month.
     *
     * @return array{values: array<string, mixed>, lines: list<PayslipLine>}|null
     */
    public function calculate(Employee $employee, CarbonImmutable $from, CarbonImmutable $to, Money $carried, ?string $carriedFrom): ?array
    {
        $start = $employee->hire_date->toDateString() > $from->toDateString() ? $employee->hire_date : $from;
        $end = $employee->termination_date !== null && $employee->termination_date->toDateString() < $to->toDateString() ? $employee->termination_date : $to;
        if ($start->toDateString() > $end->toDateString()) {
            return null;
        }

        $daysPerMonth = Config::integer('hr.payroll.days_per_month');
        $calendarDays = (int) $start->startOfDay()->diffInDays($end->startOfDay()) + 1;
        $fullMonth = $start->toDateString() === $from->toDateString() && $end->toDateString() === $to->toDateString();
        $factor = $fullMonth ? BigDecimal::one() : BigDecimal::of(min($calendarDays, $daysPerMonth))->dividedBy($daysPerMonth, 6, RoundingMode::HALF_UP);

        $basicSalary = $employee->basic_salary->getAmount();
        $daily = $basicSalary->dividedBy($daysPerMonth, 6, RoundingMode::HALF_UP);
        $summary = $this->attendance->monthlySummary($employee, $from->year, $from->month);

        $lines = [new PayslipLine(PayslipItemType::Basic, __('Basic salary'), $this->money($basicSalary->multipliedBy($factor)))];
        $taxable = $lines[0]->amount;
        $other = [];
        foreach ($this->components($employee, $from, $to) as $component) {
            $amount = $this->money($component->amount->getAmount()->multipliedBy($factor));
            if ($component->type === SalaryComponentType::Allowance) {
                $lines[] = new PayslipLine(PayslipItemType::Allowance, $component->name, $amount);
                $taxable = $component->is_taxable ? $taxable->plus($amount) : $taxable;
            } else {
                $other[] = new PayslipLine(PayslipItemType::Deduction, $component->name, $amount);
            }
        }
        // Each approved day keeps the amount it was priced at (HR rate per hour, or hourly wage × multiplier).
        $overtimeHours = BigDecimal::of($summary->approvedOvertimeMinutes)->dividedBy(60, 6, RoundingMode::HALF_UP);
        $overtime = $summary->approvedOvertimeAmount;
        $lines[] = new PayslipLine(PayslipItemType::Overtime, __('Overtime (:hours h)', ['hours' => (string) $overtimeHours->toScale(2, RoundingMode::HALF_UP)]), $overtime);

        // Doctors on commission: their share of the month's revenue (or per completed visit).
        $doctor = Doctor::query()->with('person')->find($employee->id);
        $commission = $doctor === null || $doctor->commission_type === CommissionType::None ? null : DoctorEarnings::of($doctor, $from, $to);
        if ($commission !== null && $commission->commission->isPositive()) {
            $lines[] = new PayslipLine(PayslipItemType::Commission, $commission->type === CommissionType::Percent
                ? __('Commission (:rate% of :revenue)', ['rate' => (float) $commission->rate, 'revenue' => MoneyHelper::format($commission->revenue)])
                : __('Commission (:visits visits)', ['visits' => $commission->visits]), $commission->commission);
        }

        // With late excuses on, late minutes HR did not excuse are deducted at the hourly wage.
        if ($this->settings->enabled(SettingKey::LateExcuse) && $summary->unexcusedLateMinutes > 0) {
            $lines[] = new PayslipLine(PayslipItemType::Lateness, __('Late arrival (:minutes min)', ['minutes' => $summary->unexcusedLateMinutes]), $this->pay->lateness($employee, $summary->unexcusedLateMinutes));
        }

        $absence = $this->money($daily->multipliedBy($summary->absentDays));
        $unpaid = $this->money($daily->multipliedBy($summary->unpaidLeaveDays));
        $rate = $this->insuranceRate($employee);
        $insurance = MoneyHelper::percentage($taxable, $rate);
        $lines[] = new PayslipLine(PayslipItemType::Absence, __('Absence (:days d)', ['days' => $summary->absentDays]), $absence);
        $lines[] = new PayslipLine(PayslipItemType::UnpaidLeave, __('Unpaid leave (:days d)', ['days' => $summary->unpaidLeaveDays]), $unpaid);
        $lines[] = new PayslipLine(PayslipItemType::SocialInsurance, __('Social insurance (:rate%)', ['rate' => $rate]), $insurance);
        array_push($lines, ...$other);
        if ($carried->isPositive()) {
            $lines[] = new PayslipLine(PayslipItemType::Adjustment, __('Carried from :run', ['run' => (string) $carriedFrom]), $carried);
        }
        $lines = array_values(array_filter($lines, fn (PayslipLine $l): bool => $l->type === PayslipItemType::Basic || $l->amount->isPositive()));

        $sum = fn (PayslipItemType ...$types): Money => MoneyHelper::sum(...array_map(fn (PayslipLine $l): Money => $l->amount, array_filter($lines, fn (PayslipLine $l): bool => in_array($l->type, $types, true))));
        $gross = $sum(PayslipItemType::Basic, PayslipItemType::Allowance, PayslipItemType::Overtime, PayslipItemType::Commission);
        $applied = AppliedDeductions::of($gross, $sum(PayslipItemType::Absence, PayslipItemType::Lateness, PayslipItemType::UnpaidLeave), $sum(PayslipItemType::SocialInsurance), $sum(PayslipItemType::Deduction, PayslipItemType::Adjustment));

        return [
            'values' => [
                'employee_id' => $employee->id,
                'department_id' => $employee->department_id,
                'basic_salary' => $employee->basic_salary,
                'working_days' => $summary->workingDays,
                'present_days' => $summary->presentDays,
                'absent_days' => $summary->absentDays,
                'unpaid_leave_days' => $summary->unpaidLeaveDays,
                'paid_leave_days' => $summary->paidLeaveDays,
                'overtime_minutes' => $summary->approvedOvertimeMinutes,
                'total_allowances' => $sum(PayslipItemType::Allowance),
                'overtime_amount' => $overtime,
                'gross_salary' => $gross,
                'total_deductions' => $applied->totalDeductions(),
                'net_salary' => $applied->net,
                'carried_forward' => $applied->carriedForward,
            ],
            'lines' => $lines,
        ];
    }

    /**
     * Recurring allowances and deductions in force at any time in the month.
     *
     * @return iterable<EmployeeSalaryComponent>
     */
    private function components(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): iterable
    {
        return EmployeeSalaryComponent::query()->where('employee_id', $employee->id)
            ->whereDate('effective_from', '<=', $to->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from->toDateString()))
            ->orderBy('type')->orderBy('name')->get();
    }

    /** Employee share in percent for the person's nationality. */
    private function insuranceRate(Employee $employee): string
    {
        /** @var array<string, string> $rates */
        $rates = Config::array('hr.payroll.social_insurance_rates');
        $nationality = (string) $employee->loadMissing('person')->person->nationality_code;

        return (string) ($rates[$nationality] ?? $rates['default'] ?? '0');
    }

    private function money(BigDecimal $amount): Money
    {
        return MoneyHelper::of($amount->toScale(3, RoundingMode::HALF_UP));
    }
}
