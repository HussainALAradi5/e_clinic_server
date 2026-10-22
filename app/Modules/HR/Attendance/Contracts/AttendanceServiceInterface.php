<?php

declare(strict_types=1);

namespace App\Modules\HR\Attendance\Contracts;

use App\Modules\HR\Attendance\Data\AttendanceSummaryData;
use App\Modules\HR\Attendance\Data\ManualAttendanceData;
use App\Modules\HR\Attendance\Models\Attendance;
use App\Modules\HR\Employees\Models\Employee;
use App\Shared\Query\ListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AttendanceServiceInterface
{
    /**
     * By date range, employee, status; staff see their own days.
     *
     * @return LengthAwarePaginator<int, Attendance>|CursorPaginator<int, Attendance>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** The employee's row for today, if any (check-in widget). */
    public function today(Employee $employee): ?Attendance;

    /** Self check-in from the clinic network; lateness after the shift start + grace. */
    public function checkIn(Employee $employee, string $ip): Attendance;

    /** Self check-out; worked and overtime minutes (overtime waits for approval). */
    public function checkOut(Employee $employee, string $ip): Attendance;

    /** HR creates or corrects a day (reason required); not in a month with an approved payroll. */
    public function recordManual(ManualAttendanceData $data): Attendance;

    /** A manager approves overtime for payroll — never their own. */
    public function approveOvertime(Attendance $attendance): Attendance;

    /** Totals for payroll: worked, absent, leave days (paid / unpaid), late minutes, approved overtime. */
    public function monthlySummary(Employee $employee, int $year, int $month): AttendanceSummaryData;

    /**
     * Marks the employee's days on leave (approved leave); a day already checked in is left as worked.
     *
     * @param  list<CarbonImmutable>  $days
     */
    public function markLeave(Employee $employee, array $days): void;

    /**
     * Removes leave marks of a cancelled leave (days nobody checked in).
     *
     * @param  list<CarbonImmutable>  $days
     */
    public function clearLeave(Employee $employee, array $days): void;

    /** **Scheduled 23:55** — fills the day for everyone without a row (weekend / holiday / on leave / absent) and flags missing check-outs; returns rows created. */
    public function closeDay(CarbonImmutable $date): int;

    /** Locks or unlocks a month's attendance (payroll approval / cancellation). */
    public function lockMonth(int $year, int $month, bool $locked): int;
}
