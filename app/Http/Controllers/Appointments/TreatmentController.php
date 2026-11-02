<?php

declare(strict_types=1);

namespace App\Http\Controllers\Appointments;

use App\Enums\Appointments\TreatmentPriority;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Appointments\SessionRequest;
use App\Http\Requests\Appointments\TreatmentRequest;
use App\Http\Resources\Appointments\AppointmentResource;
use App\Http\Resources\Appointments\SessionResource;
use App\Http\Resources\Appointments\TreatmentResource;
use App\Interfaces\Appointments\TreatmentServiceInterface;
use App\Models\Appointments\Treatment;
use App\Utilities\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class TreatmentController
{
    use ApiResponse;

    public function __construct(private readonly TreatmentServiceInterface $treatments) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Treatment::listQueryConfig());

        return $this->paginated(TreatmentResource::collection($this->treatments->list($query)), $query);
    }

    public function queue(Request $request): JsonResponse
    {
        $request->validate(['doctor_id' => ['nullable', 'uuid']]);

        return $this->ok(TreatmentResource::collection($this->treatments->queue($request->filled('doctor_id') ? $request->string('doctor_id')->toString() : null)));
    }

    public function show(string $treatment): JsonResponse
    {
        return $this->ok(new TreatmentResource($this->treatments->find($treatment)));
    }

    public function sessions(string $treatment): JsonResponse
    {
        return $this->ok(SessionResource::collection($this->treatments->sessions($this->treatments->find($treatment))));
    }

    public function bookSession(SessionRequest $request, string $treatment): JsonResponse
    {
        return $this->created(new AppointmentResource($this->treatments->bookSession($this->treatments->find($treatment), $request->toData())));
    }

    public function store(TreatmentRequest $request): JsonResponse
    {
        return $this->created(new TreatmentResource($this->treatments->create($request->toData())));
    }

    public function update(TreatmentRequest $request, string $treatment): JsonResponse
    {
        return $this->ok(new TreatmentResource($this->treatments->update($this->treatments->find($treatment), $request->toData(), $request->integer('version'))));
    }

    public function changePriority(Request $request, string $treatment): JsonResponse
    {
        $request->validate(['priority' => ['required', Rule::enum(TreatmentPriority::class)], 'reason' => ['required', 'string', 'max:500']]);

        return $this->ok(new TreatmentResource($this->treatments->changePriority(
            $this->treatments->find($treatment),
            TreatmentPriority::from($request->string('priority')->toString()),
            $request->string('reason')->toString(),
        )));
    }

    public function complete(string $treatment): JsonResponse
    {
        return $this->ok(new TreatmentResource($this->treatments->complete($this->treatments->find($treatment))));
    }

    public function cancel(Request $request, string $treatment): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->ok(new TreatmentResource($this->treatments->cancel($this->treatments->find($treatment), $request->string('reason')->toString())));
    }

    public function destroy(string $treatment): Response
    {
        $this->treatments->delete($this->treatments->find($treatment));

        return $this->noContent();
    }

    public function archive(string $treatment): JsonResponse
    {
        return $this->ok(new TreatmentResource($this->treatments->archive($this->treatments->find($treatment))));
    }

    public function unarchive(string $treatment): JsonResponse
    {
        return $this->ok(new TreatmentResource($this->treatments->unarchive($this->treatments->find($treatment))));
    }

    public function restore(string $treatment): JsonResponse
    {
        return $this->ok(new TreatmentResource($this->treatments->restore($this->treatments->findWithDeleted($treatment))));
    }
}
