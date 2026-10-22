<?php

declare(strict_types=1);

namespace App\Modules\HR\Leaves\Http\Controllers;

use App\Modules\HR\Leaves\Contracts\LeaveServiceInterface;
use App\Modules\HR\Leaves\Http\Requests\LeaveRequestRequest;
use App\Modules\HR\Leaves\Http\Requests\LeaveTypeRequest;
use App\Modules\HR\Leaves\Http\Resources\LeaveBalanceResource;
use App\Modules\HR\Leaves\Http\Resources\LeaveRequestResource;
use App\Modules\HR\Leaves\Http\Resources\LeaveTypeResource;
use App\Modules\HR\Leaves\Models\LeaveRequest;
use App\Modules\HR\Leaves\Models\LeaveType;
use App\Modules\HR\Support\StaffMember;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
use App\Shared\Support\ClinicClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LeaveController
{
    use ApiResponse;

    public function __construct(private readonly LeaveServiceInterface $leaves) {}

    public function types(Request $request): JsonResponse
    {
        return $this->ok(LeaveTypeResource::collection($this->leaves->listTypes($request->boolean('with_archived'))));
    }

    public function storeType(LeaveTypeRequest $request): JsonResponse
    {
        return $this->created(new LeaveTypeResource($this->leaves->saveType($request->toData())));
    }

    public function updateType(LeaveTypeRequest $request, string $type): JsonResponse
    {
        return $this->ok(new LeaveTypeResource($this->leaves->saveType($request->toData(), LeaveType::query()->findOrFail($type))));
    }

    public function balances(Request $request, string $employee, ClinicClock $clock): JsonResponse
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);
        $balances = $this->leaves->balances(StaffMember::visible($employee, 'leaves'), $request->integer('year', $clock->today()->year));

        return $this->ok(LeaveBalanceResource::collection($balances));
    }

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, LeaveRequest::listQueryConfig());

        return $this->paginated(LeaveRequestResource::collection($this->leaves->list($query)), $query);
    }

    public function show(string $leaveRequest): JsonResponse
    {
        return $this->ok(new LeaveRequestResource($this->leaves->find($leaveRequest)));
    }

    public function store(LeaveRequestRequest $request): JsonResponse
    {
        $employee = $request->filled('employee_id') ? StaffMember::visible($request->string('employee_id')->toString(), 'leaves') : StaffMember::current();

        return $this->created(new LeaveRequestResource($this->leaves->request($employee, $request->toData())));
    }

    public function submit(string $leaveRequest): JsonResponse
    {
        return $this->ok(new LeaveRequestResource($this->leaves->submit($this->leaves->find($leaveRequest))));
    }

    public function approve(Request $request, string $leaveRequest): JsonResponse
    {
        $request->validate(['notes' => ['nullable', 'string', 'max:1000'], 'cancel_affected' => ['sometimes', 'boolean']]);
        $notes = $request->filled('notes') ? $request->string('notes')->toString() : null;

        return $this->ok(new LeaveRequestResource($this->leaves->approve($this->leaves->find($leaveRequest), $notes, $request->boolean('cancel_affected'))));
    }

    public function reject(Request $request, string $leaveRequest): JsonResponse
    {
        $request->validate(['notes' => ['required', 'string', 'max:1000']]);

        return $this->ok(new LeaveRequestResource($this->leaves->reject($this->leaves->find($leaveRequest), $request->string('notes')->toString())));
    }

    public function cancel(string $leaveRequest): JsonResponse
    {
        return $this->ok(new LeaveRequestResource($this->leaves->cancel($this->leaves->find($leaveRequest))));
    }
}
