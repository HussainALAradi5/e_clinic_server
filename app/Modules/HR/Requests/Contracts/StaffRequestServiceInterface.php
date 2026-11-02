<?php

declare(strict_types=1);

namespace App\Modules\HR\Requests\Contracts;

use App\Enums\HR\StaffRequestType;
use App\Modules\HR\Attendance\Models\Attendance;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\HR\Requests\Models\StaffRequest;
use App\Shared\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Staff requests about attendance days: the employee asks HR to excuse a late arrival or to pay overtime (each kind
 * only while HR has it switched on); HR approves with an optional reason or rejects with a required one.
 */
interface StaffRequestServiceInterface
{
    /** Own requests, or everyone's with `staff_requests.read_all`. */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    public function find(string $id): StaffRequest;

    /**
     * The employee's recent days (last 31) that can still be asked about, with the kinds of request each allows.
     *
     * @return list<array{attendance: Attendance, types: list<StaffRequestType>}>
     */
    public function askable(Employee $employee): array;

    public function create(Employee $employee, StaffRequestType $type, string $attendanceId, ?string $reason): StaffRequest;

    public function approve(StaffRequest $request, ?string $reason = null): StaffRequest;

    public function reject(StaffRequest $request, string $reason): StaffRequest;

    /** The requester withdraws a pending request; the day may be asked about again. */
    public function cancel(StaffRequest $request): StaffRequest;
}
