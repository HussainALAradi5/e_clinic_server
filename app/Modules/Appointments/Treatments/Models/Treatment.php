<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Treatments\Models;

use App\Enums\Appointments\TreatmentPriority;
use App\Enums\Appointments\TreatmentType;
use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Doctors\Models\Doctor;
use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\Payments\Catalog\Models\BillableService;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Enums\GenericStatus;
use App\Shared\Models\BaseModel;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use App\Shared\Traits\HasVersion;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Treatment plan (e.g. 10 physiotherapy sessions). Its sessions are appointments (type treatment_session);
 * attendance and progress are computed from them. Diagnosis and plan are PHI (encrypted, patients.view_medical).
 *
 * @property string $id
 * @property string $code
 * @property TreatmentType $type
 * @property string $patient_id
 * @property string $doctor_id
 * @property ?string $appointment_id
 * @property ?string $billable_service_id
 * @property string $title
 * @property ?string $diagnosis
 * @property ?string $plan
 * @property TreatmentPriority $priority
 * @property GenericStatus $status
 * @property int $planned_sessions
 * @property int $session_duration_minutes
 * @property ?CarbonImmutable $start_date
 * @property ?CarbonImmutable $end_date
 * @property ?string $cancellation_reason
 * @property int $version
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Patient $patient
 * @property-read Doctor $doctor
 * @property-read ?Appointment $appointment
 * @property-read ?BillableService $billableService
 * @property-read Collection<int, Appointment> $sessions
 */
class Treatment extends BaseModel implements OwnedByUser
{
    use HasListQuery;
    use HasVersion;

    public static function codePrefix(): string
    {
        return 'TRT';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('treatments')
            ->sortable('code', 'title', 'priority', 'status', 'type', 'start_date', 'created_at')
            ->filterable('type', ['eq', 'in'], enum: TreatmentType::class)
            ->filterable('status', ['eq', 'in', 'nin'], enum: GenericStatus::class)
            ->filterable('priority', ['eq', 'in'], enum: TreatmentPriority::class)
            ->filterable('patient_id', ['eq'], resource: 'patients')
            ->filterable('doctor_id', ['eq', 'in'], resource: 'doctors')
            ->filterable('code', ['eq', 'starts'])
            ->searchIn('code', 'title')
            ->includes('patient.person', 'doctor.person')
            ->column('patient', 'Patient')
            ->column('title', 'Treatment')
            ->column('type', 'Type')
            ->column('priority', 'Priority')
            ->column('status', 'Status')
            ->column('doctor', 'Doctor')
            ->column('progress', 'Progress')
            ->column('created_at', 'Prescribed')
            ->dateFields('created_at', 'start_date', 'end_date')
            ->defaultSort('-created_at');
    }

    public function isOpen(): bool
    {
        return ! $this->status->isFinal();
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Doctor, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return BelongsTo<BillableService, $this>
     */
    public function billableService(): BelongsTo
    {
        return $this->belongsTo(BillableService::class)->withDeleted();
    }

    /**
     * Session appointments (every status, rescheduled originals included).
     *
     * @return HasMany<Appointment, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(Appointment::class)->orderBy('scheduled_date')->orderBy('start_time');
    }

    /** Patient (portal), prescribing doctor or a provider of one of its sessions. */
    public function isOwnedBy(Authenticatable $user): bool
    {
        $id = $user->getAuthIdentifier();

        return in_array($id, [$this->patient_id, $this->doctor_id], true)
            || $this->sessions()->where('provider_id', $id)->exists();
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

        return $query->where(fn (Builder $q) => $q->where('treatments.patient_id', $id)
            ->orWhere('treatments.doctor_id', $id)
            ->orWhereIn('treatments.id', Appointment::query()->select('treatment_id')->whereNotNull('treatment_id')->where('provider_id', $id)));
    }

    /** @return list<string> */
    public function auditMasked(): array
    {
        return ['diagnosis', 'plan'];
    }

    protected function casts(): array
    {
        return [
            'type' => TreatmentType::class,
            'priority' => TreatmentPriority::class,
            'status' => GenericStatus::class,
            'diagnosis' => 'encrypted',
            'plan' => 'encrypted',
            'planned_sessions' => 'integer',
            'session_duration_minutes' => 'integer',
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
