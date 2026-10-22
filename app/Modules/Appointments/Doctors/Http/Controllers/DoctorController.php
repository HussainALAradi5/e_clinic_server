<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Doctors\Http\Controllers;

use App\Modules\Appointments\Doctors\Contracts\DoctorServiceInterface;
use App\Modules\Appointments\Doctors\Http\Requests\DoctorRequest;
use App\Modules\Appointments\Doctors\Http\Resources\DoctorResource;
use App\Modules\Appointments\Doctors\Models\Doctor;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
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
