<?php

declare(strict_types=1);

namespace App\Models\Appointments;

use App\Interfaces\Shared\OwnedByUser;
use App\Models\Base\BaseModel;
use App\Models\HR\Employee;
use App\Traits\Shared\HasListQuery;
use App\Utilities\Query\ListQueryConfig;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sick leave certificate the doctor issues to the patient (`SLV-2026-000001`): the days off and, when the patient
 * agrees, the reason printed on it. Cancelled (with a reason), never deleted. Seen by the patient, the issuing doctor
 * and the front desk (who prints it); employers check it by its QR code (dates only).
 *
 * @property string $id
 * @property string $code
 * @property string $patient_id
 * @property string $doctor_id
 * @property ?string $appointment_id
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property int $days
 * @property ?string $reason
 * @property bool $show_reason
 * @property ?string $notes
 * @property CarbonImmutable $issued_at
 * @property ?CarbonImmutable $cancelled_at
 * @property ?string $cancel_reason
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Patient $patient
 * @property-read Employee $doctor
 * @property-read ?Appointment $appointment
 */
class SickLeave extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    public static function codePrefix(): string
    {
        return 'SLV';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('sick_leaves')
            ->sortable('code', 'start_date', 'end_date', 'days', 'issued_at')
            ->filterable('patient_id', ['eq'], resource: 'patients', label: 'Patient')
            ->filterable('doctor_id', ['eq'], resource: 'doctors', label: 'Doctor')
            ->filterable('start_date', ['gte', 'lte', 'between'], type: 'date')
            ->filterable('cancelled_at', ['null'], type: 'date', label: 'Cancelled')
            ->searchIn('code')
            ->includes('patient.person', 'doctor.person')
            ->column('code', 'Certificate')
            ->column('patient', 'Patient')
            ->column('start_date', 'From')
            ->column('end_date', 'To')
            ->column('days', 'Days')
            ->column('doctor', 'Doctor')
            ->column('issued_at', 'Issued')
            ->dateFields('start_date', 'issued_at')
            ->defaultSort('-issued_at');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'doctor_id');
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** The patient (portal) and the issuing doctor. */
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

        return $query->where(fn (Builder $q) => $q->where('sick_leaves.patient_id', $id)->orWhere('sick_leaves.doctor_id', $id));
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'days' => 'integer',
            'reason' => 'encrypted',
            'show_reason' => 'boolean',
            'issued_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'is_archived' => 'boolean',
            'is_deleted' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
