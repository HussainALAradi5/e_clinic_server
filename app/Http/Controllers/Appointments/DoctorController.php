<?php

declare(strict_types=1);

namespace App\Http\Controllers\Appointments;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Appointments\DoctorRequest;
use App\Http\Resources\Appointments\DoctorResource;
use App\Interfaces\Appointments\DoctorServiceInterface;
use App\Models\Appointments\Doctor;
use App\Utilities\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DoctorController
{
    use ApiResponse;

    public function __construct(private readonly DoctorServiceInterface $doctors) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Doctor::listQueryConfig());

        return $this->paginated(DoctorResource::collection($this->doctors->list($query)), $query);
    }

    public function show(string $doctor): JsonResponse
    {
        return $this->ok(new DoctorResource($this->doctors->find($doctor)));
    }

    public function store(DoctorRequest $request): JsonResponse
    {
        return $this->created(new DoctorResource($this->doctors->create($request->toData())));
    }

    public function update(DoctorRequest $request, string $doctor): JsonResponse
    {
        return $this->ok(new DoctorResource($this->doctors->update($this->doctors->find($doctor), $request->toData())));
    }

    public function accepting(Request $request, string $doctor): JsonResponse
    {
        $request->validate(['accepting' => ['required', 'boolean']]);

        return $this->ok(new DoctorResource($this->doctors->setAcceptingPatients($this->doctors->find($doctor), $request->boolean('accepting'))));
    }
}
