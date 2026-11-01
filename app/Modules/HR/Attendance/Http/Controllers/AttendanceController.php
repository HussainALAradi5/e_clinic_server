<?php

declare(strict_types=1);

namespace App\Modules\HR\Attendance\Http\Controllers;

use App\Modules\HR\Attendance\Contracts\AttendanceServiceInterface;
use App\Modules\HR\Attendance\Http\Requests\ClockRequest;
use App\Modules\HR\Attendance\Http\Requests\ManualAttendanceRequest;
use App\Modules\HR\Attendance\Http\Resources\AttendanceResource;
use App\Modules\HR\Attendance\Models\Attendance;
use App\Modules\HR\Support\StaffMember;
use App\Shared\Access\OwnershipScope;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
use App\Shared\Support\ClinicClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AttendanceController
{
    use ApiResponse;

    public function __construct(private readonly AttendanceServiceInterface $attendance) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Attendance::listQueryConfig());

        return $this->paginated(AttendanceResource::collection($this->attendance->list($query)), $query);
    }

    /** Today's row and shift of the signed-in employee (check-in widget). */
    public function today(): JsonResponse
    {
        $employee = StaffMember::current();
        $row = $this->attendance->today($employee);

        return $this->ok([
            'attendance' => $row === null ? null : new AttendanceResource($row),
            'shift' => ['start' => substr($employee->work_start_time, 0, 5), 'end' => substr($employee->work_end_time, 0, 5)],
        ]);
    }

    public function checkIn(ClockRequest $request): JsonResponse
    {
        return $this->ok(new AttendanceResource($this->attendance->checkIn(StaffMember::current(), (string) $request->ip(), $request->location())));
    }

    public function checkOut(ClockRequest $request): JsonResponse
    {
        return $this->ok(new AttendanceResource($this->attendance->checkOut(StaffMember::current(), (string) $request->ip(), $request->location())));
    }

    public function manual(ManualAttendanceRequest $request): JsonResponse
    {
        return $this->ok(new AttendanceResource($this->attendance->recordManual($request->toData())));
    }

    public function approveOvertime(string $attendance): JsonResponse
    {
        $row = OwnershipScope::apply(Attendance::query(), 'attendance', Auth::user())->findOrFail($attendance);

        return $this->ok(new AttendanceResource($this->attendance->approveOvertime($row)));
    }

    public function summary(Request $request, string $employee, ClinicClock $clock): JsonResponse
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100'], 'month' => ['nullable', 'integer', 'between:1,12']]);
        $today = $clock->today();
        $summary = $this->attendance->monthlySummary(StaffMember::visible($employee, 'attendance'), $request->integer('year', $today->year), $request->integer('month', $today->month));

        return $this->ok($summary->toArray());
    }
}
