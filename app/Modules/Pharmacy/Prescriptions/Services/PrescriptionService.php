<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Prescriptions\Services;

use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Doctors\Models\Doctor;
use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\Appointments\Treatments\Models\Treatment;
use App\Modules\Pharmacy\Medications\Models\Medication;
use App\Modules\Pharmacy\Prescriptions\Contracts\PrescriptionServiceInterface;
use App\Modules\Pharmacy\Prescriptions\Data\PrescriptionData;
use App\Modules\Pharmacy\Prescriptions\Data\PrescriptionItemData;
use App\Modules\Pharmacy\Prescriptions\Events\PrescriptionCreated;
use App\Modules\Pharmacy\Prescriptions\Exceptions\AllergyWarningException;
use App\Modules\Pharmacy\Prescriptions\Exceptions\DuplicateTherapyWarningException;
use App\Modules\Pharmacy\Prescriptions\Models\Prescription;
use App\Modules\Pharmacy\Prescriptions\Models\PrescriptionItem;
use App\Shared\Access\OwnershipScope;
use App\Shared\Enums\GenericStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Query\ListQuery;
use App\Shared\Support\ClinicClock;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PrescriptionService implements PrescriptionServiceInterface
{
    public const RELATIONS = ['patient.person', 'doctor.person', 'items.medication'];

    public function __construct(private readonly ApplyListQuery $listQuery, private readonly ClinicClock $clock) {}

    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->query(), $query);
    }

    public function queue(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->query()->queue($this->clock->today()), $query);
    }

    public function find(string $id): Prescription
    {
        return $this->query()->with([
            'appointment', 'treatment',
            'items' => fn ($q) => $q->withDispensed()->with('medication.billableService'),
            'dispensations' => fn ($q) => $q->with(['items.batch', 'dispenser.person'])->latest('dispensed_at'),
        ])->findOrFail($id);
    }

    public function create(PrescriptionData $data): Prescription
    {
        $prescription = DB::transaction(function () use ($data): Prescription {
            $patient = Patient::query()->findOrFail($data->patientId);
            $this->assertContext($data, $patient);
            $this->warn($data, $patient);

            $prescription = Prescription::query()->create([
                'patient_id' => $patient->id,
                'doctor_id' => $this->prescriber($data),
                'appointment_id' => $data->appointmentId,
                'treatment_id' => $data->treatmentId,
                'valid_until' => $data->validUntil ?? $this->clock->today()->addDays(Config::integer('pharmacy.prescription_valid_days')),
                'notes' => $data->notes,
                'status' => GenericStatus::Pending,
            ]);
            $prescription->items()->createMany(array_map(fn (PrescriptionItemData $i): array => $i->toAttributes(), $data->items));

            return $prescription;
        });
        PrescriptionCreated::dispatch($prescription);

        return $this->find($prescription->id);
    }

    public function update(Prescription $prescription, PrescriptionData $data): Prescription
    {
        DB::transaction(function () use ($prescription, $data): void {
            $prescription = Prescription::query()->lockForUpdate()->findOrFail($prescription->id);
            if ($prescription->status !== GenericStatus::Pending || $prescription->dispensations()->where('status', GenericStatus::Completed->value)->exists()) {
                throw new InvalidStateTransitionException(context: ['from' => $prescription->status->value, 'to' => 'edited']);
            }
            if ($data->patientId !== $prescription->patient_id) {
                throw ValidationException::withMessages(['patient_id' => [__('validation.in', ['attribute' => 'patient'])]]);
            }
            $patient = Patient::query()->findOrFail($prescription->patient_id);
            $this->assertContext($data, $patient);
            $this->warn($data, $patient, $prescription->id);

            $prescription->fill([
                'appointment_id' => $data->appointmentId,
                'treatment_id' => $data->treatmentId,
                'valid_until' => $data->validUntil ?? $prescription->valid_until,
                'notes' => $data->notes,
            ])->save();
            $prescription->items()->delete();
            $prescription->items()->createMany(array_map(fn (PrescriptionItemData $i): array => $i->toAttributes(), $data->items));
        });

        return $this->find($prescription->id);
    }

    public function cancel(Prescription $prescription, string $reason): Prescription
    {
        DB::transaction(function () use ($prescription, $reason): void {
            $prescription = Prescription::query()->lockForUpdate()->findOrFail($prescription->id);
            if (! $prescription->canTransitionTo(GenericStatus::Cancelled)) {
                throw new InvalidStateTransitionException(context: ['from' => $prescription->status->value, 'to' => GenericStatus::Cancelled->value]);
            }
            $prescription->cancel($reason);
        });

        return $this->find($prescription->id);
    }

    /**
     * @return Builder<Prescription>
     */
    private function query(): Builder
    {
        return OwnershipScope::apply(Prescription::query()->with(self::RELATIONS), 'prescriptions', Auth::user());
    }

    /** The signed-in doctor prescribes for themself; anyone else (with the right) names the doctor. */
    private function prescriber(PrescriptionData $data): string
    {
        $id = Auth::id();
        if (is_string($id) && Doctor::query()->whereKey($id)->exists()) {
            return $id;
        }

        return $data->doctorId ?? throw ValidationException::withMessages(['doctor_id' => [__('validation.required', ['attribute' => 'doctor'])]]);
    }

    /** The visit and treatment must be the patient's own. */
    private function assertContext(PrescriptionData $data, Patient $patient): void
    {
        if ($data->appointmentId !== null && ! Appointment::query()->whereKey($data->appointmentId)->where('patient_id', $patient->id)->exists()) {
            throw ValidationException::withMessages(['appointment_id' => [__('validation.exists', ['attribute' => 'appointment'])]]);
        }
        if ($data->treatmentId !== null && ! Treatment::query()->whereKey($data->treatmentId)->where('patient_id', $patient->id)->exists()) {
            throw ValidationException::withMessages(['treatment_id' => [__('validation.exists', ['attribute' => 'treatment'])]]);
        }
    }

    /** Duplicate therapy (another open prescription of the same medicine) and recorded allergies, unless confirmed. */
    private function warn(PrescriptionData $data, Patient $patient, ?string $exceptId = null): void
    {
        /** @var Collection<int, Medication> $medications */
        $medications = Medication::query()->whereIn('id', array_map(fn (PrescriptionItemData $i): string => $i->medicationId, $data->items))->get();

        if (! $data->overrideDuplicate) {
            $open = PrescriptionItem::query()
                ->whereIn('medication_id', $medications->modelKeys())
                ->whereHas('prescription', fn (Builder $q) => $q->where('patient_id', $patient->id)
                    ->when($exceptId !== null, fn (Builder $q) => $q->whereKeyNot($exceptId))
                    ->queue($this->clock->today()))
                ->pluck('medication_id')
                ->unique();
            if ($open->isNotEmpty()) {
                $names = $medications->whereIn('id', $open->all())->map->displayName()->implode(', ');
                throw new DuplicateTherapyWarningException(__('errors.DUPLICATE_THERAPY', ['medications' => $names]), ['medication_ids' => $open->values()->all()]);
            }
        }

        $allergies = mb_strtolower((string) $patient->allergies);
        if (! $data->overrideAllergy && trim($allergies) !== '') {
            $hits = $medications->filter(fn (Medication $m): bool => collect([$m->generic_name, $m->brand_name])
                ->filter()
                ->contains(fn (string $name): bool => str_contains($allergies, mb_strtolower($name))));
            if ($hits->isNotEmpty()) {
                throw new AllergyWarningException(__('errors.ALLERGY_WARNING', ['medications' => $hits->map->displayName()->implode(', ')]), ['medication_ids' => $hits->modelKeys()]);
            }
        }
    }
}
