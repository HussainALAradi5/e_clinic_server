<?php

declare(strict_types=1);

namespace App\Models\Appointments;

use App\Dtos\Shared\ChargeLine;
use App\Enums\Appointments\AppointmentSource;
use App\Enums\Appointments\AppointmentStatus;
use App\Enums\Appointments\AppointmentType;
use App\Enums\Appointments\CancelledBy;
use App\Enums\Appointments\VisitTiming;
use App\Interfaces\Shared\Chargeable;
use App\Interfaces\Shared\OwnedByUser;
use App\Models\Base\BaseModel;
use App\Models\HR\Employee;
use App\Models\Payments\BillableService;
use App\Traits\Payments\BilledOnInvoice;
use App\Traits\Shared\HasListQuery;
use App\Traits\Shared\HasVersion;
use App\Utilities\Appointments\CareTeam;
use App\Utilities\Query\ListQueryConfig;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Config;

/**
 * Every time-slotted visit: consultation, follow-up, procedure, emergency, teleconsultation, treatment session.
 * Times are clinic local time; the status follows AppointmentStatus::transitions().
 *
 * @property string $id
 * @property string $code
 * @property string $patient_id
 * @property string $provider_id
 * @property AppointmentType $type
 * @property ?string $treatment_id
 * @property ?int $session_number
 * @property CarbonImmutable $scheduled_date
 * @property string $start_time
 * @property string $end_time
 * @property ?string $room_id
 * @property ?string $billable_service_id
 * @property AppointmentStatus $status
 * @property bool $is_attended
 * @property AppointmentSource $source
 * @property ?string $reason
 * @property ?string $notes
 * @property ?string $rescheduled_from_id
 * @property ?CancelledBy $cancelled_by
 * @property ?string $cancellation_reason
 * @property ?CarbonImmutable $cancelled_at
 * @property ?CarbonImmutable $checked_in_at
 * @property ?CarbonImmutable $started_at
 * @property ?CarbonImmutable $completed_at
 * @property int $version
 * @property ?string $created_by
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Patient $patient
 * @property-read Employee $provider
 * @property-read ?Room $room
 * @property-read ?BillableService $billableService
 * @property-read ?Appointment $rescheduledFrom
 */
class Appointment extends BaseModel implements Chargeable, OwnedByUser
{
    use BilledOnInvoice;
    use HasListQuery;
    use HasVersion;

    public static function codePrefix(): string
    {
        return 'APT';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('appointments')
            ->sortable('code', 'scheduled_date', 'start_time', 'status', 'type', 'created_at')
            ->filterable('scheduled_date', ['eq', 'gte', 'lte', 'between'], type: 'date')
            ->filterable('status', ['eq', 'in', 'nin'], enum: AppointmentStatus::class)
            ->filterable('type', ['eq', 'in'], enum: AppointmentType::class)
            ->filterable('source', ['eq', 'in'], enum: AppointmentSource::class)
            ->filterable('provider_id', ['eq', 'in'], resource: 'doctors')
            ->filterable('patient_id', ['eq'], resource: 'patients')
            ->filterable('room_id', ['eq', 'in', 'null'], resource: 'rooms')
            ->filterable('treatment_id', ['eq', 'null'], hidden: true)
            ->filterable('code', ['eq', 'starts'])
            ->searchIn('reason')
            ->includes('patient.person', 'provider.person', 'room')
            ->column('code', 'Code')
            ->column('scheduled_date', 'Date')
            ->column('start_time', 'Time')
            ->column('timing', 'When')
            ->column('patient', 'Patient')
            ->column('provider', 'Doctor / therapist')
            ->column('type', 'Type')
            ->column('room', 'Room')
            ->column('status', 'Status')
            ->column('source', 'Source')
            ->dateFields('scheduled_date', 'created_at')
            ->maxDateRangeDays(366)
            ->defaultSort('scheduled_date,start_time');
    }

    /** Start instant (times are clinic local). */
    /** Check-in opens this long before the start (config appointments.check_in_early_minutes), on the visit's day. */
    public function checkInOpensAt(): CarbonImmutable
    {
        return $this->startsAt()->subMinutes(Config::integer('appointments.check_in_early_minutes'));
    }

    /** Whether the patient may still cancel or reschedule online (up to appointments.patient_change_hours before). */
    public function patientMayChange(CarbonImmutable $now): bool
    {
        return $now->lte($this->startsAt()->subHours(Config::integer('appointments.patient_change_hours')));
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->scheduled_date->toDateString().' '.$this->start_time, Config::string('clinic.timezone'));
    }

    public function endsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->scheduled_date->toDateString().' '.$this->end_time, Config::string('clinic.timezone'));
    }

    /** Still holds its slot (provider, room, patient). */
    public function isActive(): bool
    {
        return $this->status->holdsSlot();
    }

    /**
     * Bookings that hold their slot and overlap [start, end) on a date.
     *
     * @param  Builder<Appointment>  $query
     * @return Builder<Appointment>
     */
    public function scopeOverlapping(Builder $query, CarbonImmutable $date, string $start, string $end): Builder
    {
        return $query->whereDate('scheduled_date', $date->toDateString())
            ->whereIn('status', AppointmentStatus::slotHoldingValues())
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start);
    }

    /** Coming, today, past or missed (a no-show), seen from [today] (clinic date). */
    public function timing(CarbonImmutable $today): VisitTiming
    {
        $date = $this->scheduled_date->toDateString();

        return match (true) {
            $this->status === AppointmentStatus::NoShow => VisitTiming::Missed,
            $date === $today->toDateString() => VisitTiming::Today,
            $date > $today->toDateString() => VisitTiming::Coming,
            default => VisitTiming::Past,
        };
    }

    /**
     * Bookings of one [timing], seen from [today] (clinic date); the same rule as timing().
     *
     * @param  Builder<Appointment>  $query
     * @return Builder<Appointment>
     */
    public function scopeTiming(Builder $query, VisitTiming $timing, CarbonImmutable $today): Builder
    {
        $date = $today->toDateString();
        $noShow = AppointmentStatus::NoShow->value;

        return match ($timing) {
            VisitTiming::Missed => $query->where('status', $noShow),
            VisitTiming::Today => $query->whereDate('scheduled_date', $date)->where('status', '!=', $noShow),
            VisitTiming::Coming => $query->whereDate('scheduled_date', '>', $date)->where('status', '!=', $noShow),
            VisitTiming::Past => $query->whereDate('scheduled_date', '<', $date)->where('status', '!=', $noShow),
        };
    }

    public function chargePatientId(): string
    {
        return $this->patient_id;
    }

    /** The visit's price-list service (consultation / follow-up of the doctor, or the treatment's service). */
    public function chargeLines(): array
    {
        if ($this->billable_service_id === null) {
            return [];
        }
        $doctor = Doctor::query()->whereKey($this->provider_id)->exists() ? $this->provider_id : null;

        return [new ChargeLine($this->billable_service_id, '1', null, $doctor)];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'provider_id');
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withDeleted();
    }

    /**
     * @return BelongsTo<BillableService, $this>
     */
    public function billableService(): BelongsTo
    {
        return $this->belongsTo(BillableService::class)->withDeleted();
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_from_id');
    }

    /**
     * Visit notes (same id).
     *
     * @return HasOne<MedicalRecord, $this>
     */
    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class, 'id');
    }

    /** Patient's own booking (portal) or the provider's. */
    public function isOwnedBy(Authenticatable $user): bool
    {
        return in_array($user->getAuthIdentifier(), [$this->patient_id, $this->provider_id], true);
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

        // A patient's whole visit history is open to the clinicians caring for them (CareTeam).
        return $query->where(fn (Builder $q) => $q->where('appointments.patient_id', $id)->orWhere('appointments.provider_id', $id)
            ->orWhereIn('appointments.patient_id', CareTeam::patientIds($user)));
    }

    protected function casts(): array
    {
        return [
            'type' => AppointmentType::class,
            'status' => AppointmentStatus::class,
            'source' => AppointmentSource::class,
            'cancelled_by' => CancelledBy::class,
            'scheduled_date' => 'immutable_date',
            'session_number' => 'integer',
            'is_attended' => 'boolean',
            'cancelled_at' => 'immutable_datetime',
            'checked_in_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
