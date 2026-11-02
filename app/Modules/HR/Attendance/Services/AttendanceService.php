<?php

declare(strict_types=1);

namespace App\Modules\HR\Attendance\Services;

use App\Enums\HR\AttendanceSource;
use App\Enums\HR\AttendanceStatus;
use App\Enums\HR\EmployeeStatus;
use App\Enums\HR\PayrollStatus;
use App\Enums\HR\ShiftType;
use App\Modules\HR\Attendance\Contracts\AttendanceServiceInterface;
use App\Modules\HR\Attendance\Data\AttendanceSummaryData;
use App\Modules\HR\Attendance\Data\ClockLocationData;
use App\Modules\HR\Attendance\Data\ManualAttendanceData;
use App\Modules\HR\Attendance\Exceptions\AlreadyCheckedInException;
use App\Modules\HR\Attendance\Exceptions\AttendanceLockedException;
use App\Modules\HR\Attendance\Exceptions\CheckInNetworkException;
use App\Modules\HR\Attendance\Exceptions\NotCheckedInException;
use App\Modules\HR\Attendance\Models\Attendance;
use App\Modules\HR\Employees\Exceptions\EmployeeTerminatedException;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\HR\Leaves\Models\LeaveRequest;
use App\Modules\HR\Payroll\Models\PayrollRun;
use App\Modules\HR\Support\WorkCalendar;
use App\Modules\Identity\Notifications\Contracts\NotificationServiceInterface;
use App\Shared\Access\OwnershipScope;
use App\Shared\Enums\ApprovalStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Exceptions\SelfApprovalException;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Query\ListQuery;
use App\Shared\Support\ClinicClock;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Daily attendance: self check-in / check-out from the clinic network, HR corrections, overtime approval, the nightly
 * close that records weekends, holidays, leave and absences, and the monthly totals payroll is built on.
 */
final class AttendanceService implements AttendanceServiceInterface
{
    public function __construct(
        private readonly ApplyListQuery $listQuery,
        private readonly ClinicClock $clock,
        private readonly WorkCalendar $calendar,
        private readonly NotificationServiceInterface $notifications,
    ) {}

    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        $base = OwnershipScope::apply(Attendance::query()->with(['employee.person', 'employee.department', 'employee.shift']), 'attendance', Auth::user());

        return $this->listQuery->paginate($base, $query);
    }

    public function today(Employee $employee): ?Attendance
    {
        return Attendance::query()->where('employee_id', $employee->id)->whereDate('date', $this->clock->today()->toDateString())->first();
    }

    public function checkIn(Employee $employee, string $ip, ?ClockLocationData $location = null): Attendance
    {
        $source = $this->assertCanClock($employee, $ip, $location);

        return DB::transaction(function () use ($employee, $ip, $source): Attendance {
            $today = $this->clock->today();
            $this->assertUnlocked($today);
            $row = $this->row($employee, $today, lock: true);
            if ($row->check_in_at !== null) {
                throw new AlreadyCheckedInException(__('errors.ATTENDANCE_ALREADY_CHECKED_IN', ['time' => $this->clock->toClinic($row->check_in_at)->format('H:i')]));
            }
            $now = $this->clock->now();
            $late = $this->lateMinutes($employee, $today, $now);
            $row->fill([
                'check_in_at' => $now,
                'late_minutes' => $late,
                'status' => $late > 0 ? AttendanceStatus::Late : AttendanceStatus::Present,
                'source' => $source,
                'check_in_ip' => $ip,
                'needs_review' => false,
            ])->save();
            DB::afterCommit(fn () => $this->tellShift($employee, $row, 'checked_in'));

            return $row->load(['employee.person', 'employee.shift']);
        });
    }

    public function checkOut(Employee $employee, string $ip, ?ClockLocationData $location = null): Attendance
    {
        $this->assertCanClock($employee, $ip, $location);

        return DB::transaction(function () use ($employee): Attendance {
            $today = $this->clock->today();
            $row = Attendance::query()->where('employee_id', $employee->id)->whereDate('date', $today->toDateString())->lockForUpdate()->first();
            if ($row === null || $row->check_in_at === null || $row->check_out_at !== null) {
                throw new NotCheckedInException;
            }
            $this->assertUnlocked($today);
            $now = $this->clock->now();
            $row->fill([
                'check_out_at' => $now,
                'worked_minutes' => $this->minutes($row->check_in_at, $now),
                'overtime_minutes' => $this->overtimeMinutes($employee, $today, $now, $row->check_in_at),
                'overtime_approved' => false,
            ])->save();
            DB::afterCommit(fn () => $this->tellShift($employee, $row, 'checked_out'));

            return $row->load(['employee.person', 'employee.shift']);
        });
    }

    public function recordManual(ManualAttendanceData $data): Attendance
    {
        $employee = Employee::query()->findOrFail($data->employeeId);

        return DB::transaction(function () use ($employee, $data): Attendance {
            $this->assertUnlocked($data->date);
            $row = $this->row($employee, $data->date, lock: true);
            if ($data->checkIn !== null) {
                $in = $this->clock->combine($data->date, $data->checkIn);
                $out = $data->checkOut !== null ? $this->clock->combine($data->date, $data->checkOut) : null;
                $late = $this->lateMinutes($employee, $data->date, $in);
                $row->fill([
                    'check_in_at' => $in,
                    'check_out_at' => $out,
                    'late_minutes' => $late,
                    'worked_minutes' => $out === null ? 0 : $this->minutes($in, $out),
                    'overtime_minutes' => $out === null ? 0 : $this->overtimeMinutes($employee, $data->date, $out, $in),
                    'status' => $data->status === AttendanceStatus::HalfDay ? AttendanceStatus::HalfDay : ($late > 0 ? AttendanceStatus::Late : AttendanceStatus::Present),
                ]);
            } else {
                $row->fill([
                    'check_in_at' => null, 'check_out_at' => null, 'late_minutes' => 0, 'worked_minutes' => 0, 'overtime_minutes' => 0,
                    'status' => $data->status ?? AttendanceStatus::Absent,
                ]);
            }
            $row->fill(['source' => AttendanceSource::Manual, 'notes' => $data->reason, 'overtime_approved' => false, 'needs_review' => false])->save();

            return $row->load(['employee.person', 'employee.shift']);
        });
    }

    public function approveOvertime(Attendance $attendance): Attendance
    {
        return DB::transaction(function () use ($attendance): Attendance {
            $row = Attendance::query()->lockForUpdate()->findOrFail($attendance->id);
            if ($row->employee_id === Auth::id()) {
                throw new SelfApprovalException;
            }
            $this->assertUnlocked($row->date, $row);
            if (! $row->hasPendingOvertime()) {
                throw new InvalidStateTransitionException(context: ['from' => $row->overtime_approved ? 'approved' : 'none', 'to' => 'approved']);
            }
            $row->forceFill(['overtime_approved' => true])->save();

            return $row->load(['employee.person', 'employee.shift']);
        });
    }

    public function monthlySummary(Employee $employee, int $year, int $month): AttendanceSummaryData
    {
        $from = CarbonImmutable::parse(sprintf('%04d-%02d-01', $year, $month))->startOfDay();
        $to = $from->endOfMonth()->startOfDay();
        $rows = Attendance::query()->where('employee_id', $employee->id)
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())->get();
        $half = BigDecimal::of('0.5');
        $count = fn (AttendanceStatus ...$statuses): BigDecimal => BigDecimal::of($rows->filter(fn (Attendance $a): bool => in_array($a->status, $statuses, true))->count());
        $halfDays = $count(AttendanceStatus::HalfDay)->multipliedBy($half);
        [$paidLeave, $unpaidLeave] = $this->leaveDays($employee, $from, $to);

        return new AttendanceSummaryData(
            workingDays: count($this->calendar->workingDays($employee, $from, $to)),
            presentDays: (string) $count(AttendanceStatus::Present, AttendanceStatus::Late)->plus($halfDays)->toScale(1),
            absentDays: (string) $count(AttendanceStatus::Absent)->plus($halfDays)->toScale(1),
            paidLeaveDays: (string) $paidLeave->toScale(1),
            unpaidLeaveDays: (string) $unpaidLeave->toScale(1),
            lateMinutes: (int) $rows->sum('late_minutes'),
            approvedOvertimeMinutes: (int) $rows->where('overtime_approved', true)->sum('overtime_minutes'),
            pendingOvertimeMinutes: (int) $rows->where('overtime_approved', false)->sum('overtime_minutes'),
        );
    }

    public function markLeave(Employee $employee, array $days): void
    {
        foreach ($days as $day) {
            $row = $this->row($employee, $day);
            if ($row->check_in_at !== null || $row->is_locked) {
                continue;
            }
            $row->fill(['status' => AttendanceStatus::OnLeave, 'source' => AttendanceSource::Manual, 'needs_review' => false])->save();
        }
    }

    public function clearLeave(Employee $employee, array $days): void
    {
        if ($days === []) {
            return;
        }
        Attendance::query()->where('employee_id', $employee->id)
            ->where('status', AttendanceStatus::OnLeave->value)->whereNull('check_in_at')->where('is_locked', false)
            ->whereIn(DB::raw('date(date)'), array_map(fn (CarbonInterface $d): string => $d->toDateString(), $days))
            ->get()->each(fn (Attendance $a) => $a->delete());
    }

    public function closeDay(CarbonImmutable $date): int
    {
        return DB::transaction(function () use ($date): int {
            $day = $date->toDateString();
            $rows = Attendance::query()->whereDate('date', $day)->get()->keyBy('employee_id');
            $holidays = $this->calendar->holidays($date, $date);
            $onLeave = LeaveRequest::query()->where('status', ApprovalStatus::Approved->value)
                ->whereDate('start_date', '<=', $day)->whereDate('end_date', '>=', $day)->pluck('employee_id')->flip();
            $created = 0;

            foreach (Employee::query()->where('status', EmployeeStatus::Active->value)->get() as $employee) {
                if (! $this->calendar->isEmployed($employee, $date)) {
                    continue;
                }
                $row = $rows->get($employee->id);
                if ($row instanceof Attendance) {
                    if ($row->check_in_at !== null && $row->check_out_at === null && ! $row->is_locked) {
                        $row->fill(['status' => AttendanceStatus::HalfDay, 'needs_review' => true])->save();
                    }

                    continue;
                }
                $status = $this->calendar->dayOff($employee, $date, $holidays)
                    ?? ($onLeave->has($employee->id) ? AttendanceStatus::OnLeave : AttendanceStatus::Absent);
                Attendance::query()->create(['employee_id' => $employee->id, 'date' => $day, 'status' => $status, 'source' => AttendanceSource::Manual]);
                $created++;
            }

            return $created;
        });
    }

    public function lockMonth(int $year, int $month, bool $locked): int
    {
        $from = CarbonImmutable::parse(sprintf('%04d-%02d-01', $year, $month));

        return Attendance::query()
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $from->endOfMonth()->toDateString())
            ->update(['is_locked' => $locked]);
    }

    /**
     * Approved leave days of the month split into paid and unpaid (half days count 0.5).
     *
     * @return array{0: BigDecimal, 1: BigDecimal}
     */
    private function leaveDays(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $paid = BigDecimal::zero();
        $unpaid = BigDecimal::zero();
        $requests = LeaveRequest::query()->with('leaveType')->where('employee_id', $employee->id)->where('status', ApprovalStatus::Approved->value)
            ->whereDate('start_date', '<=', $to->toDateString())->whereDate('end_date', '>=', $from->toDateString())->get();
        foreach ($requests as $request) {
            $start = $request->start_date->max($from);
            $end = $request->end_date->min($to);
            $days = $request->is_half_day ? BigDecimal::of('0.5') : BigDecimal::of(count($this->calendar->workingDays($employee, $start, $end)));
            $request->leaveType->is_paid ? $paid = $paid->plus($days) : $unpaid = $unpaid->plus($days);
        }

        return [$paid, $unpaid];
    }

    /**
     * Clocking happens at the clinic: from its network (web), or with the phone's position inside the clinic
     * geofence when one is set (device). Returns how it was proven.
     */
    private function assertCanClock(Employee $employee, string $ip, ?ClockLocationData $location): AttendanceSource
    {
        if ($employee->isTerminated()) {
            throw new EmployeeTerminatedException;
        }
        /** @var list<string> $networks */
        $networks = Config::array('hr.attendance.networks');
        if ($networks === [] || IpUtils::checkIp($ip, $networks)) {
            return AttendanceSource::Web;
        }
        if ($location !== null && $this->insideGeofence($location)) {
            return AttendanceSource::Device;
        }
        throw new CheckInNetworkException;
    }

    private function insideGeofence(ClockLocationData $location): bool
    {
        $latitude = Config::get('hr.attendance.geofence.latitude');
        $longitude = Config::get('hr.attendance.geofence.longitude');
        $radius = Config::integer('hr.attendance.geofence.radius_meters', 150);
        if (! is_float($latitude) || ! is_float($longitude) || $radius <= 0) {
            return false;
        }
        // A vague reading (accuracy wider than the circle) could be anywhere around it.
        if ($location->accuracy !== null && $location->accuracy > $radius) {
            return false;
        }

        return $location->distanceTo($latitude, $longitude) <= $radius;
    }

    /** Months covered by an approved or paid payroll are read-only. */
    private function assertUnlocked(CarbonInterface $date, ?Attendance $row = null): void
    {
        $locked = ($row !== null && $row->is_locked) || PayrollRun::query()
            ->where('period_year', $date->year)->where('period_month', $date->month)
            ->whereIn('status', [PayrollStatus::Approved->value, PayrollStatus::Paid->value])->exists();
        if ($locked) {
            throw new AttendanceLockedException(__('errors.ATTENDANCE_LOCKED', ['month' => $date->format('F Y')]));
        }
    }

    private function row(Employee $employee, CarbonInterface $date, bool $lock = false): Attendance
    {
        $existing = Attendance::query()->where('employee_id', $employee->id)->whereDate('date', $date->toDateString())
            ->when($lock, fn (Builder $q) => $q->lockForUpdate())->first();

        return $existing ?? new Attendance(['employee_id' => $employee->id, 'date' => $date->toDateString(), 'status' => AttendanceStatus::Absent, 'source' => AttendanceSource::Manual]);
    }

    private function lateMinutes(Employee $employee, CarbonInterface $date, CarbonImmutable $at): int
    {
        // A shift decides (the start, or a flexible shift's latest start); otherwise the employee's own hours.
        $start = $this->clock->combine($date, $employee->shift?->arriveBy() ?? substr($employee->work_start_time, 0, 5));
        $late = $this->minutes($start, $at);

        return $late > Config::integer('hr.attendance.late_grace_minutes') ? $late : 0;
    }

    private function overtimeMinutes(Employee $employee, CarbonInterface $date, CarbonImmutable $out, ?CarbonInterface $in = null): int
    {
        $shift = $employee->shift;
        // Flexible: whatever was worked beyond the hours due. Fixed (or no shift): after the end of the day.
        $overtime = $shift?->type === ShiftType::Flexible && $in !== null
            ? $this->minutes($in, $out) - $shift->required_minutes
            : $this->minutes($this->clock->combine($date, $shift?->expectedEnd() ?? substr($employee->work_end_time, 0, 5)), $out);

        return $overtime >= Config::integer('hr.attendance.overtime_min_minutes') ? $overtime : 0;
    }

    /**
     * Tells the employee how the day stands against their shift: on time or late at check-in; on time, overtime
     * (waiting for approval) or left early at check-out.
     */
    private function tellShift(Employee $employee, Attendance $row, string $moment): void
    {
        $shift = $employee->shift;
        $at = $this->clock->toClinic($moment === 'checked_in' ? $row->check_in_at : $row->check_out_at);
        $end = $this->clock->combine($row->date, $shift?->expectedEnd() ?? substr($employee->work_end_time, 0, 5));
        $early = $moment === 'checked_out' && $row->overtime_minutes === 0
            ? ($shift?->type === ShiftType::Flexible ? max(0, $shift->required_minutes - $row->worked_minutes) : $this->minutes($at, $end))
            : 0;
        $verdict = match (true) {
            $moment === 'checked_in' && $row->late_minutes > 0 => 'late',
            $moment === 'checked_out' && $row->overtime_minutes > 0 => 'overtime',
            $moment === 'checked_out' && $early >= Config::integer('hr.attendance.late_grace_minutes') => 'early',
            default => 'on_time',
        };
        $this->notifications->send("attendance.{$moment}_{$verdict}", $employee->person, $row, [
            'time' => $at->format('H:i'),
            'shift' => $shift === null ? __('notifications.attendance.your_hours') : "{$shift->name} ({$shift->code})",
            'hours' => ($shift?->arriveBy() ?? substr($employee->work_start_time, 0, 5)).'–'.($shift?->expectedEnd() ?? substr($employee->work_end_time, 0, 5)),
            'late' => $this->duration($row->late_minutes),
            'overtime' => $this->duration($row->overtime_minutes),
            'early' => $this->duration($early),
            'worked' => $this->duration($row->worked_minutes),
        ]);
    }

    /** "1 h 05 min", "40 min". */
    private function duration(int $minutes): string
    {
        return $minutes >= 60 ? sprintf('%d h %02d min', intdiv($minutes, 60), $minutes % 60) : "{$minutes} min";
    }

    /** Whole minutes from → to, 0 when to is not after from. */
    private function minutes(CarbonInterface $from, CarbonInterface $to): int
    {
        return max(0, intdiv($to->getTimestamp() - $from->getTimestamp(), 60));
    }
}
