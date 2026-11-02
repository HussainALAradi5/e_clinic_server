<?php

declare(strict_types=1);

namespace App\Http\Controllers\Appointments;

use App\Http\Concerns\ApiResponse;
use App\Http\Resources\Appointments\SickLeaveResource;
use App\Interfaces\Appointments\AppointmentServiceInterface;
use App\Models\Appointments\SickLeave;
use App\Services\Appointments\SickLeaveService;
use App\Utilities\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Sick leave certificates: issued at a visit, listed (own / all), printed, cancelled by the issuing doctor. */
final class SickLeaveController
{
    use ApiResponse;

    public function __construct(
        private readonly SickLeaveService $sickLeaves,
        private readonly AppointmentServiceInterface $appointments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, SickLeave::listQueryConfig());

        return $this->paginated(SickLeaveResource::collection($this->sickLeaves->list($query)), $query);
    }

    public function show(string $sickLeave): JsonResponse
    {
        return $this->ok(new SickLeaveResource($this->sickLeaves->find($sickLeave)));
    }

    /** `start_date`, `end_date`, optional `reason`, `show_reason` (print it), `notes`. */
    public function store(Request $request, string $appointment): JsonResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:500'],
            'show_reason' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->created(new SickLeaveResource($this->sickLeaves->issue(
            $this->appointments->find($appointment),
            (string) $request->user()?->getAuthIdentifier(),
            $data,
        )));
    }

    public function cancel(Request $request, string $sickLeave): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        return $this->ok(new SickLeaveResource($this->sickLeaves->cancel(
            $this->sickLeaves->find($sickLeave),
            (string) $request->user()?->getAuthIdentifier(),
            $request->string('reason')->toString(),
        )));
    }
}
