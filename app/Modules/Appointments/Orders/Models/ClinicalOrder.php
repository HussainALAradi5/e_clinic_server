<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Orders\Models;

use App\Enums\Appointments\ClinicalOrderType;
use App\Enums\Appointments\TreatmentPriority;
use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Doctors\Models\Doctor;
use App\Modules\Appointments\Doctors\Models\Specialty;
use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\Appointments\Rooms\Models\Room;
use App\Modules\Appointments\Treatments\Models\Treatment;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\Identity\Attachments\Models\Attachment;
use App\Modules\Payments\Catalog\Models\BillableService;
use App\Modules\Payments\Invoices\Concerns\BilledOnInvoice;
use App\Modules\Reference\Companies\Models\Company;
use App\Shared\Contracts\Chargeable;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Contracts\ReleasedToPatient;
use App\Shared\Data\ChargeLine;
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
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Imaging / lab / ECG order or referral. Images and result files are its attachments; notes and the report are PHI.
 * The code (`ORD-YYYY-NNNNNN`) is printed as the barcode on samples and films.
 *
 * @property string $id
 * @property string $code
 * @property ClinicalOrderType $type
 * @property string $patient_id
 * @property string $ordered_by
 * @property ?string $appointment_id
 * @property ?string $treatment_id
 * @property ?string $billable_service_id
 * @property ?string $referred_to_doctor_id
 * @property ?string $referred_to_specialty_id
 * @property ?string $referred_to_company_id
 * @property ?string $referral_appointment_id
 * @property ?string $body_part
 * @property ?string $clinical_notes
 * @property TreatmentPriority $priority
 * @property GenericStatus $status
 * @property ?string $room_id
 * @property ?string $performed_by
 * @property ?CarbonImmutable $started_at
 * @property ?CarbonImmutable $completed_at
 * @property ?string $report
 * @property ?string $reported_by
 * @property ?bool $is_abnormal
 * @property ?string $cancellation_reason
 * @property int $version
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Patient $patient
 * @property-read Doctor $orderedBy
 * @property-read ?Appointment $appointment
 * @property-read ?Treatment $treatment
 * @property-read ?BillableService $billableService
 * @property-read ?Doctor $referredToDoctor
 * @property-read ?Specialty $referredToSpecialty
 * @property-read ?Company $referredToCompany
 * @property-read ?Appointment $referralAppointment
 * @property-read ?Room $room
 * @property-read ?Employee $performer
 * @property-read ?Doctor $reporter
 * @property-read Collection<int, Attachment> $attachments
 */
class ClinicalOrder extends BaseModel implements Chargeable, OwnedByUser, ReleasedToPatient
{
    use BilledOnInvoice;
    use HasListQuery;
    use HasVersion;

    public static function codePrefix(): string
    {
        return 'ORD';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('orders')
            ->sortable('code', 'type', 'priority', 'status', 'created_at', 'completed_at')
            ->filterable('type', ['eq', 'in', 'nin'], enum: ClinicalOrderType::class)
            ->filterable('status', ['eq', 'in', 'nin'], enum: GenericStatus::class)
            ->filterable('priority', ['eq', 'in'], enum: TreatmentPriority::class)
            ->filterable('patient_id', ['eq'], resource: 'patients')
            ->filterable('ordered_by', ['eq', 'in'], resource: 'doctors')
            ->filterable('appointment_id', ['eq'], hidden: true)
            ->filterable('room_id', ['eq', 'null'], resource: 'rooms')
            ->filterable('is_abnormal', ['eq'], type: 'boolean', label: 'Abnormal result')
            ->filterable('code', ['eq', 'starts'])
            ->searchIn('code', 'body_part')
            ->includes('patient.person', 'orderedBy.person', 'billableService')
            ->column('code', 'Code')
            ->column('patient', 'Patient')
            ->column('type', 'Type')
            ->column('description', 'Test / referral')
            ->column('priority', 'Priority')
            ->column('status', 'Status')
            ->column('ordered_by', 'Ordered by')
            ->column('created_at', 'Ordered')
            ->dateFields('created_at', 'completed_at')
            ->defaultSort('-created_at');
    }

    public function chargePatientId(): string
    {
        return $this->patient_id;
    }

    /** The order's price-list service, credited to the ordering doctor (referrals are not billed). */
    public function chargeLines(): array
    {
        return $this->isReferral() || $this->billable_service_id === null ? [] : [new ChargeLine($this->billable_service_id, '1', null, $this->ordered_by)];
    }

    public function isReferral(): bool
    {
        return $this->type === ClinicalOrderType::Referral;
    }

    /** Referral to another hospital (letter + reply) instead of an internal booking. */
    public function isExternalReferral(): bool
    {
        return $this->isReferral() && $this->referred_to_company_id !== null;
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Doctor, $this> */
    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'ordered_by');
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

    /** @return BelongsTo<BillableService, $this> */
    public function billableService(): BelongsTo
    {
        return $this->belongsTo(BillableService::class)->withDeleted();
    }

    /** @return BelongsTo<Doctor, $this> */
    public function referredToDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'referred_to_doctor_id');
    }

    /** @return BelongsTo<Specialty, $this> */
    public function referredToSpecialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class, 'referred_to_specialty_id')->withDeleted();
    }

    /** @return BelongsTo<Company, $this> */
    public function referredToCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'referred_to_company_id')->withDeleted();
    }

    /** @return BelongsTo<Appointment, $this> */
    public function referralAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'referral_appointment_id');
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withDeleted();
    }

    /** @return BelongsTo<Employee, $this> */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'performed_by');
    }

    /** @return BelongsTo<Doctor, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'reported_by');
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** Patient (portal), ordering doctor, the technician who performed it or the doctor it refers to. */
    public function isOwnedBy(Authenticatable $user): bool
    {
        return in_array($user->getAuthIdentifier(), [$this->patient_id, $this->ordered_by, $this->performed_by, $this->referred_to_doctor_id], true);
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

        return $query->where(fn (Builder $q) => $q->where('clinical_orders.patient_id', $id)
            ->orWhere('clinical_orders.ordered_by', $id)
            ->orWhere('clinical_orders.performed_by', $id)
            ->orWhere('clinical_orders.referred_to_doctor_id', $id));
    }

    /** The report and images of a completed order belong to its patient too (portal results). */
    public function isReleasedTo(Authenticatable $user): bool
    {
        return $this->status === GenericStatus::Completed && $user->getAuthIdentifier() === $this->patient_id;
    }

    /** @return list<string> */
    public function auditMasked(): array
    {
        return ['clinical_notes', 'report'];
    }

    protected function casts(): array
    {
        return [
            'type' => ClinicalOrderType::class,
            'priority' => TreatmentPriority::class,
            'status' => GenericStatus::class,
            'clinical_notes' => 'encrypted',
            'report' => 'encrypted',
            'is_abnormal' => 'boolean',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
