<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Patients\Services;

use App\Enums\Appointments\AppointmentType;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Enums\Reference\CompanyType;
use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Orders\Models\ClinicalOrder;
use App\Modules\Appointments\Patients\Contracts\PatientServiceInterface;
use App\Modules\Appointments\Patients\Data\PatientData;
use App\Modules\Appointments\Patients\Data\PatientTimelineEntry;
use App\Modules\Appointments\Patients\Events\EmergencyAccessGranted;
use App\Modules\Appointments\Patients\Events\PatientRegistered;
use App\Modules\Appointments\Patients\Exceptions\DuplicatePatientException;
use App\Modules\Appointments\Patients\Exceptions\PatientAlreadyHasAccountException;
use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\Appointments\Patients\Models\PatientAccessGrant;
use App\Modules\Appointments\Treatments\Models\Treatment;
use App\Modules\Identity\Users\Contracts\UserServiceInterface;
use App\Modules\Identity\Users\Data\CreateUserData;
use App\Modules\Identity\Users\Data\PersonData;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Identity\Users\Models\User;
use App\Modules\Reference\Companies\Models\Company;
use App\Shared\Access\OwnershipScope;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Query\ListQuery;
use App\Shared\Support\BlindIndex;
use App\Shared\Support\ClinicClock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PatientService implements PatientServiceInterface
{
    /** How long a break-the-glass grant lasts (a shift). */
    private const EMERGENCY_ACCESS_HOURS = 12;

    public function __construct(
        private readonly ApplyListQuery $listQuery,
        private readonly UserServiceInterface $users,
        private readonly ClinicClock $clock,
    ) {}

    public function list(ListQuery $query, ?string $nationalId = null): LengthAwarePaginator|CursorPaginator
    {
        $builder = $this->patients()->when(filled($nationalId), fn (Builder $q) => $q->where(
            'persons.national_id_hash',
            BlindIndex::make((string) $nationalId, Person::BLIND_INDEX_CONTEXT),
        ));

        return $this->listQuery->paginate($builder, $query);
    }

    public function find(string $id): Patient
    {
        return $this->patients()
            ->with(['person.nationality', 'person.address.country', 'user'])
            ->withCount(['appointments as upcoming_appointments_count' => fn (Builder $q) => $q
                ->whereDate('scheduled_date', '>=', $this->clock->today()->toDateString())
                ->whereIn('status', ['pending', 'confirmed'])])
            ->withCount('appointments')
            ->findOrFail($id);
    }

    public function create(PatientData $data): Patient
    {
        $this->assertInsurer($data->values['insurance_company_id'] ?? null);

        $patient = DB::transaction(function () use ($data): Patient {
            $person = $data->personId !== null
                ? Person::query()->findOrFail($data->personId)
                : $this->users->findOrCreatePerson($data->person ?? throw ValidationException::withMessages(['person' => [__('validation.required', ['attribute' => 'person'])]]), $data->forceCreate);

            if (Patient::query()->whereKey($person->id)->exists()) {
                throw new DuplicatePatientException(__('errors.PATIENT_ALREADY_REGISTERED', ['code' => $person->code]), ['patient_id' => $person->id, 'code' => $person->code]);
            }

            return Patient::query()->create([...$data->values, 'id' => $person->id]);
        });

        PatientRegistered::dispatch($patient);

        return $this->find($patient->id);
    }

    public function update(Patient $patient, PatientData $data, int $version): Patient
    {
        if (array_key_exists('insurance_company_id', $data->values)) {
            $this->assertInsurer($data->values['insurance_company_id']);
        }

        return DB::transaction(function () use ($patient, $data, $version): Patient {
            $locked = Patient::query()->lockForUpdate()->findOrFail($patient->id);
            $locked->assertVersion($version);
            $locked->fill($data->values)->save();

            return $this->find($locked->id);
        });
    }

    public function updateContact(Patient $patient, ?PersonData $person, int $personVersion, ?PatientData $data, int $version): Patient
    {
        return DB::transaction(function () use ($patient, $person, $personVersion, $data, $version): Patient {
            if ($person !== null) {
                $this->users->updatePerson($patient->person, $person, $personVersion);
            }

            return $data !== null ? $this->update($patient, $data, $version) : $this->find($patient->id);
        });
    }

    public function createPortalAccount(Patient $patient): User
    {
        if (User::query()->whereKey($patient->id)->exists()) {
            throw new PatientAlreadyHasAccountException;
        }

        return $this->users->create($patient->person, new CreateUserData(UserType::Patient, [RoleName::Patient->value]));
    }

    public function timeline(Patient $patient): Collection
    {
        $user = Auth::user();
        $entries = collect();

        if ($user?->can('appointments.read_all') || $user?->can('appointments.read_own')) {
            $appointments = OwnershipScope::apply(Appointment::query(), 'appointments', $user)
                ->where('patient_id', $patient->id)
                ->with(['provider.person'])
                ->orderByDesc('scheduled_date')->orderByDesc('start_time')
                ->limit(200)
                ->get();

            foreach ($appointments as $a) {
                $entries->push(new PatientTimelineEntry(
                    kind: $a->type === AppointmentType::TreatmentSession ? 'session' : 'appointment',
                    id: $a->id,
                    at: $a->startsAt(),
                    title: $a->type->label().' with '.$a->provider->person->full_name,
                    status: $a->status->value,
                    data: ['code' => $a->code, 'provider_id' => $a->provider_id, 'reason' => $a->reason],
                ));
            }
        }

        if ($user?->can('treatments.read_all') || $user?->can('treatments.read_own')) {
            OwnershipScope::apply(Treatment::query(), 'treatments', $user)
                ->where('patient_id', $patient->id)->with('doctor.person')->latest()->limit(100)->get()
                ->each(fn (Treatment $t) => $entries->push(new PatientTimelineEntry(
                    kind: 'treatment',
                    id: $t->id,
                    at: $t->created_at ?? CarbonImmutable::now(),
                    title: $t->title.' ('.$t->planned_sessions.' sessions)',
                    status: $t->status->value,
                    data: ['code' => $t->code, 'priority' => $t->priority->value, 'doctor' => $t->doctor->person->full_name],
                )));
        }

        if ($user?->can('orders.read_all') || $user?->can('orders.read_own')) {
            OwnershipScope::apply(ClinicalOrder::query(), 'orders', $user)
                ->where('patient_id', $patient->id)->with(['billableService', 'orderedBy.person'])->latest()->limit(100)->get()
                ->each(fn (ClinicalOrder $o) => $entries->push(new PatientTimelineEntry(
                    kind: $o->isReferral() ? 'referral' : 'order',
                    id: $o->id,
                    at: $o->completed_at ?? $o->created_at ?? CarbonImmutable::now(),
                    title: $o->billableService->name ?? $o->type->label(),
                    status: $o->status->value,
                    data: ['code' => $o->code, 'type' => $o->type->value, 'is_abnormal' => $o->is_abnormal, 'ordered_by' => $o->orderedBy->person->full_name],
                )));
        }

        return $entries->sortByDesc(fn (PatientTimelineEntry $e): int => $e->at->getTimestamp())->values();
    }

    public function lookup(?string $code, ?string $nationalId): ?Patient
    {
        if (blank($code) && blank($nationalId)) {
            return null;
        }
        $patient = Patient::query()
            ->select('patients.*')
            ->join('persons', 'persons.id', '=', 'patients.id')
            ->where('persons.is_deleted', false)
            ->when(filled($code), fn (Builder $q) => $q->where('persons.code', mb_strtoupper(trim((string) $code))))
            ->when(filled($nationalId), fn (Builder $q) => $q->where('persons.national_id_hash', BlindIndex::make((string) $nationalId, Person::BLIND_INDEX_CONTEXT)))
            ->with('person')
            ->first();
        if ($patient !== null) {
            $patient->setAttribute('in_care', $this->patients()->whereKey($patient->id)->exists());
        }

        return $patient;
    }

    public function grantEmergencyAccess(Patient $patient, User $clinician, string $reason): PatientAccessGrant
    {
        $grant = DB::transaction(fn (): PatientAccessGrant => PatientAccessGrant::query()->create([
            'patient_id' => $patient->id,
            'user_id' => $clinician->id,
            'reason' => $reason,
            'expires_at' => $this->clock->now()->addHours(self::EMERGENCY_ACCESS_HOURS),
        ]));
        EmergencyAccessGranted::dispatch($patient->loadMissing('person'), $grant);

        return $grant;
    }

    /**
     * @return Builder<Patient>
     */
    private function patients(): Builder
    {
        $query = Patient::query()
            ->select('patients.*')
            ->join('persons', 'persons.id', '=', 'patients.id')
            ->where('persons.is_deleted', false)
            ->with(['person', 'insurer']);

        return OwnershipScope::apply($query, 'patients', Auth::user());
    }

    private function assertInsurer(mixed $companyId): void
    {
        if ($companyId !== null && ! Company::query()->whereKey($companyId)->where('type', CompanyType::Insurer)->exists()) {
            throw ValidationException::withMessages(['insurance_company_id' => [__('validation.exists', ['attribute' => 'insurer'])]]);
        }
    }
}
