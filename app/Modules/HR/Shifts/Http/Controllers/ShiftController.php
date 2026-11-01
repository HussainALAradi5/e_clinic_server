<?php

declare(strict_types=1);

namespace App\Modules\HR\Shifts\Http\Controllers;

use App\Modules\HR\Shifts\Contracts\ShiftServiceInterface;
use App\Modules\HR\Shifts\Http\Requests\ShiftRequest;
use App\Modules\HR\Shifts\Http\Resources\ShiftResource;
use App\Modules\HR\Shifts\Models\Shift;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Work shifts and who is on them (HR and admins). */
final class ShiftController
{
    use ApiResponse;

    public function __construct(private readonly ShiftServiceInterface $shifts) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Shift::listQueryConfig());

        return $this->paginated(ShiftResource::collection($this->shifts->list($query)), $query);
    }

    public function show(string $shift): JsonResponse
    {
        return $this->ok(new ShiftResource($this->shifts->findShift($shift)));
    }

    public function store(ShiftRequest $request): JsonResponse
    {
        return $this->created(new ShiftResource($this->shifts->createShift($request->toData())));
    }

    public function update(ShiftRequest $request, string $shift): JsonResponse
    {
        return $this->ok(new ShiftResource($this->shifts->updateShift($this->shifts->findShift($shift), $request->toData())));
    }

    public function destroy(string $shift): Response
    {
        $this->shifts->deleteShift($this->shifts->findShift($shift));

        return $this->noContent();
    }

    /** `employee_ids[]`: put them on this shift (moving them from any other). */
    public function assign(Request $request, string $shift): JsonResponse
    {
        $request->validate(['employee_ids' => ['required', 'array', 'min:1', 'max:200'], 'employee_ids.*' => ['uuid', 'distinct']]);

        return $this->ok(new ShiftResource($this->shifts->assign($this->shifts->findShift($shift), array_values(array_map('strval', (array) $request->input('employee_ids'))))));
    }

    public function unassign(string $shift, string $employee): JsonResponse
    {
        return $this->ok(new ShiftResource($this->shifts->unassign($this->shifts->findShift($shift), $employee)));
    }
}
