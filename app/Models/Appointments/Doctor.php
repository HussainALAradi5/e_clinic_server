<?php

declare(strict_types=1);

namespace App\Models\Appointments;

use App\Enums\HR\EmployeeStatus;
use App\Interfaces\Shared\Auditable;
use App\Interfaces\Shared\OwnedByUser;
use App\Models\HR\Employee;
use App\Models\Identity\Person;
use App\Models\Payments\BillableService;
use App\Traits\Shared\Auditable as AuditsChanges;
use App\Traits\Shared\HasListQuery;
use App\Utilities\Query\ListQueryConfig;
use App\Utilities\Shared\ClinicClock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Clinical profile of a doctor — subtype of employees (a doctor is an employee is a user is a person: one id).
 *
 * @property string $id
 * @property string $specialty_id
 * @property string $license_number
 * @property CarbonImmutable $license_expiry_date
 * @property ?string $title
 * @property string $consultation_service_id
 * @property string $follow_up_service_id
 * @property int $default_slot_minutes
 * @property ?int $max_daily_appointments
 * @property bool $is_accepting_patients
 * @property ?string $default_room_id
 * @property ?string $bio
 * @property-read Employee $employee
 * @property-read Person $person
 * @property-read Specialty $specialty
 * @property-read BillableService $consultationService
 * @property-read BillableService $followUpService
 * @property-read ?Room $defaultRoom
 */
class Doctor extends Model implements Auditable, OwnedByUser
{
    use AuditsChanges;
    use HasListQuery;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('doctors')
            ->sortable('code', 'last_name', 'first_name', 'license_expiry_date')
            ->filterable('specialty_id', ['eq', 'in'], column: 'doctors.specialty_id', resource: 'specialties')
            ->filterable('is_accepting_patients', ['eq'], column: 'doctors.is_accepting_patients', type: 'boolean')
            ->filterable('license_expiry_date', ['lte', 'gte', 'between'], column: 'doctors.license_expiry_date', type: 'date')
            ->searchIn('persons.first_name', 'persons.last_name', 'persons.code')
            ->column('code', 'Code')
            ->column('full_name', 'Name')
            ->column('specialty', 'Specialty')
            ->column('title', 'Title')
            ->column('license_number', 'Licence')
            ->column('license_expiry_date', 'Licence expires')
            ->column('is_accepting_patients', 'Taking new patients')
            ->dateFields('license_expiry_date')
            ->defaultSort('last_name,first_name');
    }

    /** Can take new bookings today: accepting, licence valid, employee active. */
    public function isBookable(?CarbonImmutable $on = null): bool
    {
        $on ??= app(ClinicClock::class)->today();

        return $this->is_accepting_patients
            && $this->license_expiry_date->toDateString() >= $on->toDateString()
            && $this->employee->status === EmployeeStatus::Active;
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'id');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'id');
    }

    /**
     * @return BelongsTo<Specialty, $this>
     */
    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class)->withDeleted();
    }

    /**
     * @return BelongsTo<BillableService, $this>
     */
    public function consultationService(): BelongsTo
    {
        return $this->belongsTo(BillableService::class, 'consultation_service_id')->withDeleted();
    }

    /**
     * @return BelongsTo<BillableService, $this>
     */
    public function followUpService(): BelongsTo
    {
        return $this->belongsTo(BillableService::class, 'follow_up_service_id')->withDeleted();
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function defaultRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'default_room_id');
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return $this->id === $user->getAuthIdentifier();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->where('doctors.id', $user->getAuthIdentifier());
    }

    protected function casts(): array
    {
        return [
            'license_expiry_date' => 'immutable_date',
            'is_accepting_patients' => 'boolean',
            'default_slot_minutes' => 'integer',
            'max_daily_appointments' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
