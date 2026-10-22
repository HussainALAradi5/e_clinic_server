<?php

declare(strict_types=1);

namespace App\Modules\HR\Support;

use App\Enums\Appointments\ScheduleExceptionType;
use App\Enums\HR\AttendanceStatus;
use App\Modules\Appointments\Schedules\Models\ScheduleException;
use App\Modules\HR\Employees\Models\Employee;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * Which days an employee is expected at work: not their weekly days off, not a clinic-wide holiday, and within their
 * employment. Shared by leave day counts, the nightly attendance close and payroll.
 */
final class WorkCalendar
{
    /**
     * Clinic holidays of the range as 'Y-m-d' => true.
     *
     * @return array<string, bool>
     */
    public function holidays(CarbonInterface $from, CarbonInterface $to): array
    {
        return ScheduleException::query()
            ->whereNull('provider_id')
            ->where('type', ScheduleExceptionType::Holiday->value)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->pluck('date')
            ->mapWithKeys(fn (CarbonInterface $d): array => [$d->toDateString() => true])
            ->all();
    }

    /**
     * Why a day is not a working day (weekend / holiday), or null when the employee is expected.
     *
     * @param  array<string, bool>  $holidays  from holidays()
     */
    public function dayOff(Employee $employee, CarbonInterface $date, array $holidays): ?AttendanceStatus
    {
        return match (true) {
            in_array($date->dayOfWeek, array_map('intval', $employee->weekly_off_days), true) => AttendanceStatus::Weekend,
            isset($holidays[$date->toDateString()]) => AttendanceStatus::Holiday,
            default => null,
        };
    }

    /** True while the person is employed on that day (hire date to termination date). */
    public function isEmployed(Employee $employee, CarbonInterface $date): bool
    {
        $day = $date->toDateString();

        return $employee->hire_date->toDateString() <= $day
            && ($employee->termination_date === null || $employee->termination_date->toDateString() >= $day);
    }

    /**
     * Working days of the employee between two dates (inclusive), within their employment.
     *
     * @return list<CarbonImmutable>
     */
    public function workingDays(Employee $employee, CarbonInterface $from, CarbonInterface $to): array
    {
        $holidays = $this->holidays($from, $to);
        $days = [];
        foreach (CarbonPeriod::create($from->toDateString(), $to->toDateString()) as $day) {
            $day = CarbonImmutable::instance($day);
            if ($this->isEmployed($employee, $day) && $this->dayOff($employee, $day, $holidays) === null) {
                $days[] = $day;
            }
        }

        return $days;
    }
}
