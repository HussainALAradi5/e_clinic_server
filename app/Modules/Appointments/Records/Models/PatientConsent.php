<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Records\Models;

use App\Enums\Appointments\ConsentType;
use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Orders\Models\ClinicalOrder;
use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\Appointments\Patients\Support\CareTeam;
use App\Modules\Appointments\Treatments\Models\Treatment;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\Identity\Attachments\Models\Attachment;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Signed consent: patient-wide, or for one visit, treatment plan or order. Valid = not deleted, not revoked and
 * not expired (computed). The signed form / signature is an attachment (category consent_form).
 *
 * @property string $id
 * @property string $code
 * @property string $patient_id
 * @property ConsentType $type
 * @property ?string $appointment_id
 * @property ?string $treatment_id
 * @property ?string $clinical_order_id
 * @property string $description
 * @property string $signed_by_name
 * @property string $signer_relationship
 * @property string $explained_by
 * @property ?string $witness_id
 * @property CarbonImmutable $signed_at
 * @property ?CarbonImmutable $valid_until
 * @property ?CarbonImmutable $revoked_at
 * @property ?string $revocation_reason
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Patient $patient
 * @property-read Employee $explainer
 * @property-read ?Employee $witness
 * @property-read ?Appointment $appointment
 * @property-read ?Treatment $treatment
 * @property-read ?ClinicalOrder $clinicalOrder
 * @property-read Collection<int, Attachment> $attachments
 */
class PatientConsent extends BaseModel implements OwnedByUser
{
    /** Consent types that always cover the whole patient file. */
    public const PATIENT_WIDE = [ConsentType::GeneralTreatment, ConsentType::DataSharing, ConsentType::Photography];

    public static function codePrefix(): string
    {
        return 'CNS';
    }

    public function isValid(CarbonImmutable $today): bool
    {
        return ! $this->is_deleted && $this->revoked_at === null
            && ($this->valid_until === null || $this->valid_until->toDateString() >= $today->toDateString());
    }

    /**
     * Valid on a day (query form of isValid()).
     *
     * @param  Builder<PatientConsent>  $query
     * @return Builder<PatientConsent>
     */
    public function scopeValidOn(Builder $query, CarbonImmutable $today): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today->toDateString()));
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function explainer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'explained_by');
    }

    /** @return BelongsTo<Employee, $this> */
    public function witness(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'witness_id');
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

    /** @return BelongsTo<ClinicalOrder, $this> */
    public function clinicalOrder(): BelongsTo
    {
        return $this->belongsTo(ClinicalOrder::class);
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return in_array($user->getAuthIdentifier(), [$this->patient_id, $this->explained_by], true);
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

        return $query->where(fn (Builder $q) => $q->where('patient_consents.patient_id', $id)
            ->orWhere('patient_consents.explained_by', $id)
            ->orWhereIn('patient_consents.patient_id', CareTeam::patientIds($user)));
    }

    protected function casts(): array
    {
        return [
            'type' => ConsentType::class,
            'signed_at' => 'immutable_datetime',
            'valid_until' => 'immutable_date',
            'revoked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
