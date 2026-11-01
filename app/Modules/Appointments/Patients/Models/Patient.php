<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Patients\Models;

use App\Enums\Appointments\BloodGroup;
use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Patients\Support\CareTeam;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Identity\Users\Models\User;
use App\Modules\Reference\Companies\Models\Company;
use App\Shared\Contracts\Auditable;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Enums\Gender;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\Auditable as AuditsChanges;
use App\Shared\Traits\HasListQuery;
use App\Shared\Traits\HasVersion;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Patient file — the medical extension of a person (same id). The person code (`PRS-…`) is the MRN.
 * Allergies, chronic conditions and family / surgical history are PHI: encrypted, masked in the audit trail, shown
 * with patients.view_medical.
 *
 * @property string $id
 * @property BloodGroup $blood_group
 * @property ?string $allergies
 * @property ?string $chronic_conditions
 * @property ?string $family_history
 * @property ?string $surgical_history
 * @property ?string $emergency_contact_name
 * @property ?string $emergency_contact_phone
 * @property ?string $emergency_contact_relation
 * @property ?string $insurance_company_id
 * @property ?string $insurance_policy_number
 * @property ?CarbonImmutable $insurance_expiry_date
 * @property ?string $insurance_coverage_percent
 * @property ?string $notes
 * @property int $version
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Person $person
 * @property-read ?User $user
 * @property-read ?Company $insurer
 */
class Patient extends Model implements Auditable, OwnedByUser
{
    use AuditsChanges;
    use HasListQuery;
    use HasVersion;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['version'];

    protected $attributes = ['blood_group' => 'unknown'];

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('patients')
            ->sortable('code', 'last_name', 'first_name', 'date_of_birth', 'created_at')
            ->filterable('gender', ['eq'], column: 'persons.gender', enum: Gender::class)
            ->filterable('insurance_company_id', ['eq', 'in', 'null'], column: 'patients.insurance_company_id', resource: 'companies', label: 'Insurer')
            ->filterable('date_of_birth', ['gte', 'lte', 'between'], column: 'persons.date_of_birth', type: 'date')
            ->filterable('created_at', ['gte', 'lte', 'between'], column: 'patients.created_at', type: 'datetime')
            ->searchIn('persons.first_name', 'persons.last_name', 'persons.first_name_ar', 'persons.last_name_ar', 'persons.code', 'persons.phone')
            ->column('code', 'Patient no.')
            ->column('full_name', 'Name')
            ->column('gender', 'Gender')
            ->column('date_of_birth', 'Date of birth')
            ->column('phone', 'Mobile')
            ->column('insurer', 'Insurer')
            ->column('created_at', 'Registered')
            ->dateFields('created_at')
            ->defaultSort('-created_at');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'id');
    }

    /**
     * Portal account (same id), if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function insurer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'insurance_company_id')->withDeleted();
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** Own file (portal) or a patient the user has / had a booking with as provider. */
    public function isOwnedBy(Authenticatable $user): bool
    {
        return static::query()->ownedBy($user)->whereKey($this->id)->exists();
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

        return $query->where(fn (Builder $q) => $q->where('patients.id', $id)->orWhereIn('patients.id', CareTeam::patientIds($user)));
    }

    /** @return list<string> */
    public function auditMasked(): array
    {
        return ['allergies', 'chronic_conditions', 'family_history', 'surgical_history'];
    }

    protected function casts(): array
    {
        return [
            'blood_group' => BloodGroup::class,
            'allergies' => 'encrypted',
            'chronic_conditions' => 'encrypted',
            'family_history' => 'encrypted',
            'surgical_history' => 'encrypted',
            'insurance_expiry_date' => 'immutable_date',
            'insurance_coverage_percent' => 'decimal:2',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
