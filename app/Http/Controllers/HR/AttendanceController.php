<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR;

use App\Enums\HR\SettingKey;
use App\Exceptions\HR\OvertimeNeedsRequestException;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\HR\ClockRequest;
use App\Http\Requests\HR\ManualAttendanceRequest;
use App\Http\Resources\HR\AttendanceResource;
use App\Interfaces\HR\AttendanceServiceInterface;
use App\Interfaces\HR\SettingServiceInterface;
use App\Models\HR\Attendance;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\HR\StaffMember;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
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
            'shift' => [
                'start' => $employee->shift?->arriveBy() ?? substr($employee->work_start_time, 0, 5),
                'end' => $employee->shift?->expectedEnd() ?? substr($employee->work_end_time, 0, 5),
                'code' => $employee->shift?->code,
                'name' => $employee->shift?->name,
                'type' => $employee->shift?->type->value,
            ],
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

    public function approveOvertime(string $attendance, SettingServiceInterface $settings): JsonResponse
    {
        // With overtime requests on, overtime is approved through the employee's request (one way to approve it).
        if ($settings->enabled(SettingKey::Overtime)) {
            throw new OvertimeNeedsRequestException;
        }
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
