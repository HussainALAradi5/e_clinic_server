<?php

declare(strict_types=1);

namespace App\Http\Controllers\Appointments;

use App\Dtos\Appointments\PatientTimelineEntry;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Appointments\EmergencyAccessRequest;
use App\Http\Requests\Appointments\MedicalHistoryRequest;
use App\Http\Requests\Appointments\PatientRequest;
use App\Http\Resources\Appointments\PatientLookupResource;
use App\Http\Resources\Appointments\PatientResource;
use App\Http\Resources\Identity\UserResource;
use App\Interfaces\Appointments\PatientServiceInterface;
use App\Models\Appointments\Patient;
use App\Models\Identity\User;
use App\Utilities\Query\ListQuery;
use Illuminate\Auth\AuthenticationException;
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

    /** Identity of a patient by file number or national ID (to confirm before breaking the glass). */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['code' => ['nullable', 'string', 'max:30', 'required_without:national_id'], 'national_id' => ['nullable', 'string', 'max:30']]);
        $patient = $this->patients->lookup($request->string('code')->toString() ?: null, $request->string('national_id')->toString() ?: null);

        return $this->ok($patient === null ? null : new PatientLookupResource($patient));
    }

    /** Break the glass: the file opens to the caller for a few hours; the grant is audited and admins are told. */
    public function emergencyAccess(EmergencyAccessRequest $request, string $patient): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }
        $grant = $this->patients->grantEmergencyAccess(Patient::query()->with('person')->findOrFail($patient), $user, $request->string('reason')->toString());

        return $this->created(['patient' => new PatientResource($this->patients->find($patient)), 'expires_at' => $grant->expires_at->toIso8601String()]);
    }

    /** Allergies, chronic conditions, family and surgical history — by the clinicians caring for the patient. */
    public function medicalHistory(MedicalHistoryRequest $request, string $patient): JsonResponse
    {
        return $this->ok(new PatientResource($this->patients->update($this->patients->find($patient), $request->toData(), $request->integer('version'))));
    }
}
