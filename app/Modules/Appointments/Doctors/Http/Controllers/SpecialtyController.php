<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Doctors\Http\Controllers;

use App\Modules\Appointments\Doctors\Contracts\SpecialtyServiceInterface;
use App\Modules\Appointments\Doctors\Http\Requests\SpecialtyRequest;
use App\Modules\Appointments\Doctors\Http\Resources\SpecialtyResource;
use App\Modules\Appointments\Doctors\Models\Specialty;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SpecialtyController
{
    use ApiResponse;

    public function __construct(private readonly SpecialtyServiceInterface $specialties) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Specialty::listQueryConfig());

        return $this->paginated(SpecialtyResource::collection($this->specialties->listSpecialties($query)), $query);
    }

    public function show(string $specialty): JsonResponse
    {
        return $this->ok(new SpecialtyResource($this->specialties->findSpecialty($specialty)));
    }

    public function store(SpecialtyRequest $request): JsonResponse
    {
        return $this->created(new SpecialtyResource($this->specialties->createSpecialty($request->toData())));
    }

    public function update(SpecialtyRequest $request, string $specialty): JsonResponse
    {
        return $this->ok(new SpecialtyResource($this->specialties->updateSpecialty($this->specialties->findSpecialty($specialty), $request->toData())));
    }

    public function destroy(string $specialty): Response
    {
        $this->specialties->deleteSpecialty($this->specialties->findSpecialty($specialty));

        return $this->noContent();
    }

    public function archive(string $specialty): JsonResponse
    {
        return $this->ok(new SpecialtyResource($this->specialties->archiveSpecialty($this->specialties->findSpecialty($specialty))));
    }

    public function unarchive(string $specialty): JsonResponse
    {
        return $this->ok(new SpecialtyResource($this->specialties->unarchiveSpecialty($this->specialties->findSpecialty($specialty))));
    }

    public function restore(string $specialty): JsonResponse
    {
        return $this->ok(new SpecialtyResource($this->specialties->restoreSpecialty($this->specialties->findWithDeletedSpecialty($specialty))));
    }
}
