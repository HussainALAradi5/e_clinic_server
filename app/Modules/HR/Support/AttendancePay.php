<?php

declare(strict_types=1);

namespace App\Modules\HR\Support;

use App\Enums\HR\SettingKey;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\HR\Settings\Contracts\SettingServiceInterface;
use App\Shared\Support\MoneyHelper;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Facades\Config;

/**
 * What a day's minutes are worth: overtime at the HR rate per hour (or the employee's hourly wage × the overtime
 * multiplier when HR set none), and late minutes at the employee's hourly wage. Used when overtime is approved (the
 * amount is kept on the day) and by payroll.
 */
final class AttendancePay
{
    public function __construct(private readonly SettingServiceInterface $settings) {}

    /** Basic salary / days per month / hours per day. */
    public function hourlyWage(Employee $employee): BigDecimal
    {
        return $employee->basic_salary->getAmount()
            ->dividedBy(Config::integer('hr.payroll.days_per_month'), 6, RoundingMode::HALF_UP)
            ->dividedBy(Config::integer('hr.payroll.hours_per_day'), 6, RoundingMode::HALF_UP);
    }

    public function overtimeRate(Employee $employee): BigDecimal
    {
        return $this->settings->amount(SettingKey::Overtime)?->getAmount()
            ?? $this->hourlyWage($employee)->multipliedBy(Config::string('hr.payroll.overtime_multiplier'));
    }

    public function overtime(Employee $employee, int $minutes): Money
    {
        return $this->money($this->overtimeRate($employee)->multipliedBy($this->hours($minutes)));
    }

    public function lateness(Employee $employee, int $minutes): Money
    {
        return $this->money($this->hourlyWage($employee)->multipliedBy($this->hours($minutes)));
    }

    private function hours(int $minutes): BigDecimal
    {
        return BigDecimal::of($minutes)->dividedBy(60, 6, RoundingMode::HALF_UP);
    }

    private function money(BigDecimal $amount): Money
    {
        return MoneyHelper::of($amount->toScale(3, RoundingMode::HALF_UP));
    }
}
