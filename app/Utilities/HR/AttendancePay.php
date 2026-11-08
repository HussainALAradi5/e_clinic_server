<?php

declare(strict_types=1);

namespace App\Utilities\HR;

use App\Enums\HR\SettingKey;
use App\Interfaces\HR\SettingServiceInterface;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\OvertimeBand;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\MoneyHelper;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Facades\Config;

/**
 * What a day's minutes are worth: overtime at the pay of the time bands it falls in, and outside every band at the HR
 * rate per hour (or the employee's hourly wage × the overtime multiplier when HR set none); late minutes at the
 * employee's hourly wage. Used when overtime is approved (the amount is kept on the day) and by payroll.
 */
final class AttendancePay
{
    private const MINUTES_PER_HOUR = 60;

    private const MINUTES_PER_DAY = 1440;

    public function __construct(
        private readonly SettingServiceInterface $settings,
        private readonly ClinicClock $clock,
    ) {}

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

    public function overtime(Employee $employee, Attendance $day): Money
    {
        return $this->money($this->overtimeValue($employee, $day));
    }

    /** The day's average pay per hour; the plain rate when no time band applies. */
    public function overtimeRateForDay(Employee $employee, Attendance $day): BigDecimal
    {
        if ($day->overtime_minutes === 0) {
            return $this->overtimeRate($employee);
        }

        return $this->overtimeValue($employee, $day)->dividedBy($this->hours($day->overtime_minutes), 6, RoundingMode::HALF_UP);
    }

    public function lateness(Employee $employee, int $minutes): Money
    {
        return $this->money($this->hourlyWage($employee)->multipliedBy($this->hours($minutes)));
    }

    /**
     * The overtime is the last `overtime_minutes` before check-out. Each band pays for the part inside it; a window
     * that started the evening before check-out is compared with the bands of that day too.
     */
    private function overtimeValue(Employee $employee, Attendance $day): BigDecimal
    {
        $baseRate = $this->overtimeRate($employee);
        $bands = $this->settings->overtimeBands();

        if ($bands->isEmpty() || $day->check_out_at === null) {
            return $baseRate->multipliedBy($this->hours($day->overtime_minutes));
        }

        $out = $this->clock->toClinic($day->check_out_at);
        $end = $out->hour * self::MINUTES_PER_HOUR + $out->minute;
        $start = $end - $day->overtime_minutes;

        $total = BigDecimal::zero();
        $covered = 0;
        foreach ($bands as $band) {
            foreach ([0, -self::MINUTES_PER_DAY] as $shift) {
                $inside = $this->overlap($start, $end, $band, $shift);
                if ($inside > 0) {
                    $total = $total->plus($band->rate->getAmount()->multipliedBy($this->hours($inside)));
                    $covered += $inside;
                }
            }
        }

        return $total->plus($baseRate->multipliedBy($this->hours($day->overtime_minutes - $covered)));
    }

    private function overlap(int $start, int $end, OvertimeBand $band, int $shift): int
    {
        return max(0, min($end, $band->end_minute + $shift) - max($start, $band->start_minute + $shift));
    }

    private function hours(int $minutes): BigDecimal
    {
        return BigDecimal::of($minutes)->dividedBy(self::MINUTES_PER_HOUR, 6, RoundingMode::HALF_UP);
    }

    private function money(BigDecimal $amount): Money
    {
        return MoneyHelper::of($amount->toScale(3, RoundingMode::HALF_UP));
    }
}
