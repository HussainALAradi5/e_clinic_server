<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Providers;

use App\Dtos\Dashboards\AttendanceDayFigures;
use App\Dtos\Dashboards\DashboardFilterDto;
use App\Dtos\Dashboards\DashboardMetric;
use App\Enums\Dashboards\AttendanceMetric;
use App\Enums\Dashboards\DashboardMetricKind;
use App\Enums\Dashboards\DashboardSection;
use App\Enums\HR\AttendanceStatus;
use App\Enums\HR\EmployeeStatus;
use App\Enums\Shared\ApprovalStatus;
use App\Interfaces\Dashboards\DashboardMetricProvider;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\LeaveRequest;
use App\Utilities\HR\WorkCalendar;
use App\Utilities\Shared\ClinicClock;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Config;
use WeakMap;

/**
 * The attendance section: one clinic day. The nightly close only writes rows for the day that ended, so a day still
 * running counts from what is known: a person with no row is on leave when an approved leave covers the day, and
 * absent once their start time and the grace period have passed (before that they are not counted).
 */
final class AttendanceMetricProvider implements DashboardMetricProvider
{
    private const READ = 'attendance.read_all';

    private const OVERTIME = 'attendance.read_all&attendance.approve_overtime';

    private const LATE_ARRIVALS_LIMIT = 20;

    /** @var WeakMap<DashboardFilterDto, AttendanceDayFigures> one computation per request, however many widgets */
    private WeakMap $days;

    public function __construct(
        private readonly ClinicClock $clock,
        private readonly WorkCalendar $calendar,
    ) {
        $this->days = new WeakMap;
    }

    public function section(): DashboardSection
    {
        return DashboardSection::Attendance;
    }

    public function metrics(): array
    {
        return [
            $this->stat(AttendanceMetric::Present, self::READ, fn (AttendanceDayFigures $d): int => $d->present),
            $this->stat(AttendanceMetric::Late, self::READ, fn (AttendanceDayFigures $d): int => $d->late),
            $this->stat(AttendanceMetric::Absent, self::READ, fn (AttendanceDayFigures $d): int => $d->absent),
            $this->stat(AttendanceMetric::OnLeave, self::READ, fn (AttendanceDayFigures $d): int => $d->onLeave),
            $this->stat(AttendanceMetric::PendingOvertime, self::OVERTIME, fn (AttendanceDayFigures $d): int => $d->pendingOvertime),
            $this->stat(AttendanceMetric::ApprovedOvertimeMinutes, self::OVERTIME, fn (AttendanceDayFigures $d): int => $d->approvedOvertimeMinutes),
            $this->stat(AttendanceMetric::NeedsReview, self::READ, fn (AttendanceDayFigures $d): int => $d->needsReview),
            new DashboardMetric(AttendanceMetric::LateArrivals->value, AttendanceMetric::LateArrivals->label(), DashboardMetricKind::List, self::READ, $this->lateArrivals(...)),
        ];
    }

    /**
     * @param  Closure(AttendanceDayFigures): int  $figure
     */
    private function stat(AttendanceMetric $metric, string $access, Closure $figure): DashboardMetric
    {
        return new DashboardMetric($metric->value, $metric->label(), DashboardMetricKind::Stat, $access, fn (DashboardFilterDto $filter): int => $figure($this->day($filter)));
    }

    private function day(DashboardFilterDto $filter): AttendanceDayFigures
    {
        return $this->days[$filter] ??= $this->figures($filter->date);
    }

    private function figures(CarbonImmutable $date): AttendanceDayFigures
    {
        $rows = Attendance::query()->whereDate('date', $date->toDateString())
            ->get(['employee_id', 'status', 'late_minutes', 'overtime_minutes', 'overtime_approved', 'needs_review']);
        $count = fn (Closure $matches): int => $rows->filter($matches)->count();
        [$absent, $onLeave] = $this->unrecorded($date, $rows->pluck('employee_id')->flip()->all());

        return new AttendanceDayFigures(
            present: $count(fn (Attendance $a): bool => $a->status->isWorked()),
            late: $count(fn (Attendance $a): bool => $a->late_minutes > 0),
            absent: $absent + $count(fn (Attendance $a): bool => $a->status === AttendanceStatus::Absent),
            onLeave: $onLeave + $count(fn (Attendance $a): bool => $a->status === AttendanceStatus::OnLeave),
            pendingOvertime: $count(fn (Attendance $a): bool => $a->hasPendingOvertime()),
            approvedOvertimeMinutes: (int) $rows->where('overtime_approved', true)->sum('overtime_minutes'),
            needsReview: $count(fn (Attendance $a): bool => $a->needs_review),
        );
    }

    /**
     * Expected people with no row yet for the day.
     *
     * @param  array<string, int>  $recorded  employee id => anything
     * @return array{0: int, 1: int} absent, on leave
     */
    private function unrecorded(CarbonImmutable $date, array $recorded): array
    {
        $day = $date->toDateString();
        $holidays = $this->calendar->holidays($date, $date);
        $leave = LeaveRequest::query()->where('status', ApprovalStatus::Approved->value)
            ->whereDate('start_date', '<=', $day)->whereDate('end_date', '>=', $day)->pluck('employee_id')->flip();
        $employees = Employee::query()->where('status', EmployeeStatus::Active->value)
            ->get(['id', 'hire_date', 'termination_date', 'work_start_time', 'weekly_off_days']);
        $now = $this->clock->now();
        $grace = Config::integer('hr.attendance.late_grace_minutes');
        $absent = 0;
        $onLeave = 0;

        foreach ($employees as $employee) {
            if (isset($recorded[$employee->id]) || ! $this->calendar->isEmployed($employee, $date) || $this->calendar->dayOff($employee, $date, $holidays) !== null) {
                continue;
            }
            if ($leave->has($employee->id)) {
                $onLeave++;
            } elseif ($now->greaterThan($this->clock->combine($date, substr($employee->work_start_time, 0, 5))->addMinutes($grace))) {
                $absent++;
            }
        }

        return [$absent, $onLeave];
    }

    /**
     * The day's late arrivals, latest first (bounded: a dashboard is not a report).
     *
     * @return list<array{id: string, title: string, subtitle: ?string, value: string}>
     */
    private function lateArrivals(DashboardFilterDto $filter): array
    {
        $rows = Attendance::query()->with(['employee.person', 'employee.department'])
            ->whereDate('date', $filter->date->toDateString())->where('late_minutes', '>', 0)
            ->orderByDesc('late_minutes')->limit(self::LATE_ARRIVALS_LIMIT)->get();

        return array_values($rows->map(fn (Attendance $row): array => [
            'id' => $row->id,
            'title' => $row->employee->person->full_name,
            'subtitle' => $row->employee->department->name,
            'value' => "{$row->late_minutes} min late",
        ])->all());
    }
}
