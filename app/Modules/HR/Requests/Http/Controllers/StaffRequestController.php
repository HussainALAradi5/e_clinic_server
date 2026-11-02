<?php

declare(strict_types=1);

namespace App\Modules\HR\Requests\Http\Controllers;

use App\Enums\HR\StaffRequestType;
use App\Modules\HR\Attendance\Http\Resources\AttendanceResource;
use App\Modules\HR\Requests\Contracts\StaffRequestServiceInterface;
use App\Modules\HR\Requests\Http\Resources\StaffRequestResource;
use App\Modules\HR\Requests\Models\StaffRequest;
use App\Modules\HR\Support\StaffMember;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StaffRequestController
{
    use ApiResponse;

    public function __construct(private readonly StaffRequestServiceInterface $requests) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, StaffRequest::listQueryConfig());

        return $this->paginated(StaffRequestResource::collection($this->requests->list($query)), $query);
    }

    public function show(string $staffRequest): JsonResponse
    {
        return $this->ok(new StaffRequestResource($this->requests->find($staffRequest)));
    }

    /** My recent days that can still be asked about, and which kinds each allows (only kinds HR switched on). */
    public function askable(): JsonResponse
    {
        return $this->ok(array_map(fn (array $row): array => [
            'attendance' => new AttendanceResource($row['attendance']),
            'types' => array_map(fn (StaffRequestType $t): string => $t->value, $row['types']),
        ], $this->requests->askable(StaffMember::current())));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', StaffRequestType::rule()],
            'attendance_id' => ['required', 'uuid'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $reason = $request->filled('reason') ? $request->string('reason')->trim()->toString() : null;
        $created = $this->requests->create(StaffMember::current(), StaffRequestType::from($request->string('type')->toString()), $request->string('attendance_id')->toString(), $reason);

        return $this->created(new StaffRequestResource($created));
    }

    public function approve(Request $request, string $staffRequest): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $reason = $request->filled('reason') ? $request->string('reason')->trim()->toString() : null;

        return $this->ok(new StaffRequestResource($this->requests->approve($this->requests->find($staffRequest), $reason)));
    }

    public function reject(Request $request, string $staffRequest): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);

        return $this->ok(new StaffRequestResource($this->requests->reject($this->requests->find($staffRequest), $request->string('reason')->trim()->toString())));
    }

    public function cancel(string $staffRequest): JsonResponse
    {
        return $this->ok(new StaffRequestResource($this->requests->cancel($this->requests->find($staffRequest))));
    }
}
