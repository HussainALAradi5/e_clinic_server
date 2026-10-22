<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Patients\Http\Controllers;

use App\Modules\Appointments\Patients\Contracts\PatientServiceInterface;
use App\Modules\Appointments\Patients\Data\PatientTimelineEntry;
use App\Modules\Appointments\Patients\Http\Requests\PatientRequest;
use App\Modules\Appointments\Patients\Http\Resources\PatientResource;
use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\Identity\Users\Http\Resources\UserResource;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PatientController
{
    use ApiResponse;

    public function __construct(private readonly PatientServiceInterface $patients) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['national_id' => ['nullable', 'string', 'max:30']]);
        $query = ListQuery::fromRequest($request, Patient::listQueryConfig());
        $nationalId = $request->filled('national_id') ? $request->string('national_id')->toString() : null;

        return $this->paginated(PatientResource::collection($this->patients->list($query, $nationalId)), $query);
    }

    public function show(string $patient): JsonResponse
    {
        return $this->ok(new PatientResource($this->patients->find($patient)));
    }

    public function store(PatientRequest $request): JsonResponse
    {
        return $this->created(new PatientResource($this->patients->create($request->toData())));
    }

    public function update(PatientRequest $request, string $patient): JsonResponse
    {
        return $this->ok(new PatientResource($this->patients->update($this->patients->find($patient), $request->toData(), $request->integer('version'))));
    }

    public function createPortalAccount(string $patient): JsonResponse
    {
        $user = $this->patients->createPortalAccount($this->patients->find($patient));

        return $this->created(new UserResource($user->load('person', 'roles')));
    }

    public function timeline(string $patient): JsonResponse
    {
        $entries = $this->patients->timeline($this->patients->find($patient));

        return $this->ok($entries->map(fn (PatientTimelineEntry $e): array => $e->toArray())->all());
    }
}
