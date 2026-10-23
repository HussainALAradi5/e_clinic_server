<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Prescriptions\Models;

use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Doctors\Models\Doctor;
use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\Appointments\Treatments\Models\Treatment;
use App\Modules\Pharmacy\Dispensing\Models\Dispensation;
use App\Modules\Pharmacy\Support\Qty;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Enums\GenericStatus;
use App\Shared\Models\BaseModel;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasGenericStatus;
use App\Shared\Traits\HasListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Prescription written by a doctor. Its status follows the dispensed quantities: pending (nothing yet) →
 * in_progress (partly) → completed (all); cancelling stops what is left. The code (`RX-YYYY-NNNNNN`) is the QR the
 * pharmacy scans. Notes to the pharmacist are encrypted.
 *
 * @property string $id
 * @property string $code
 * @property string $patient_id
 * @property string $doctor_id
 * @property ?string $appointment_id
 * @property ?string $treatment_id
 * @property GenericStatus $status
 * @property CarbonImmutable $valid_until
 * @property ?string $notes
 * @property ?string $cancellation_reason
 * @property ?string $created_by
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Patient $patient
 * @property-read Doctor $doctor
 * @property-read ?Appointment $appointment
 * @property-read ?Treatment $treatment
 * @property-read Collection<int, PrescriptionItem> $items
 * @property-read Collection<int, Dispensation> $dispensations
 */
class Prescription extends BaseModel implements OwnedByUser
{
    use HasGenericStatus;
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'RX';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('prescriptions')
            ->sortable('code', 'status', 'valid_until', 'created_at')
            ->filterable('status', ['eq', 'in', 'nin'], enum: GenericStatus::class)
            ->filterable('patient_id', ['eq'], resource: 'patients')
            ->filterable('doctor_id', ['eq', 'in'], resource: 'doctors')
            ->filterable('appointment_id', ['eq'], hidden: true)
            ->filterable('treatment_id', ['eq'], hidden: true)
            ->filterable('valid_until', ['lt', 'gte', 'between'], type: 'date')
            ->filterable('code', ['eq', 'starts'])
            ->searchIn('code')
            ->searchInRelation('patient.person', 'code', 'first_name', 'last_name', 'phone')
            ->includes('patient.person', 'doctor.person', 'items.medication')
            ->column('code', 'Code')
            ->column('patient', 'Patient')
            ->column('doctor', 'Doctor')
            ->column('items', 'Medicines')
            ->column('status', 'Status')
            ->column('valid_until', 'Valid until')
            ->column('created_at', 'Prescribed')
            ->dateFields('created_at', 'valid_until')
            ->defaultSort('-created_at');
    }

    /** Pending or partly dispensed and not past `valid_until`. */
    public function isDispensable(CarbonImmutable $today): bool
    {
        return in_array($this->status, [GenericStatus::Pending, GenericStatus::InProgress], true) && $this->valid_until->gte($today);
    }

    /**
     * Status from the dispensed quantities (items loaded withDispensed): none → pending, some → in_progress,
     * all → completed. Cancelled prescriptions stay cancelled.
     */
    public function syncDispensedStatus(): void
    {
        if ($this->status === GenericStatus::Cancelled) {
            return;
        }
        $items = $this->items()->withDispensed()->get();
        $given = $items->contains(fn (PrescriptionItem $i): bool => Qty::of($i->dispensed_quantity)->isPositive());
        $done = $items->every(fn (PrescriptionItem $i): bool => $i->remaining()->isZero());

        $this->forceFill(['status' => match (true) {
            $done => GenericStatus::Completed,
            $given => GenericStatus::InProgress,
            default => GenericStatus::Pending,
        }])->save();
    }

    /**
     * Open and still valid — the pharmacy queue.
     *
     * @param  Builder<Prescription>  $query
     * @return Builder<Prescription>
     */
    public function scopeQueue(Builder $query, CarbonImmutable $today): Builder
    {
        return $query->whereIn('prescriptions.status', [GenericStatus::Pending->value, GenericStatus::InProgress->value])
            ->whereDate('prescriptions.valid_until', '>=', $today->toDateString());
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Doctor, $this> */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return BelongsTo<Treatment, $this> */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    /** @return HasMany<PrescriptionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return HasMany<Dispensation, $this> */
    public function dispensations(): HasMany
    {
        return $this->hasMany(Dispensation::class);
    }

    /** Patient (portal) or the prescribing doctor. */
    public function isOwnedBy(Authenticatable $user): bool
    {
        return in_array($user->getAuthIdentifier(), [$this->patient_id, $this->doctor_id], true);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        $id = $user->getAuthIdentifier();

        return $query->where(fn (Builder $q) => $q->where('prescriptions.patient_id', $id)->orWhere('prescriptions.doctor_id', $id));
    }

    /** @return list<string> */
    public function auditMasked(): array
    {
        return ['notes'];
    }

    protected function casts(): array
    {
        return [
            'valid_until' => 'immutable_date',
            'notes' => 'encrypted',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
