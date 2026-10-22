<?php

declare(strict_types=1);

namespace App\Modules\HR\Employees\Models;

use App\Enums\HR\EmployeeStatus;
use App\Enums\HR\EmploymentType;
use App\Enums\HR\TerminationReason;
use App\Modules\HR\Departments\Models\Department;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Casts\MoneyCast;
use App\Shared\Contracts\Auditable;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\Auditable as AuditsChanges;
use App\Shared\Traits\HasListQuery;
use App\Shared\Traits\HasVersion;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * HR record of a staff member — subtype of users (and persons) with the **same id**. Personal data lives in persons,
 * account data in users; this row holds employment data only.
 *
 * @property string $id
 * @property string $department_id
 * @property string $job_title
 * @property EmploymentType $employment_type
 * @property EmployeeStatus $status
 * @property CarbonImmutable $hire_date
 * @property ?CarbonImmutable $probation_end_date
 * @property ?CarbonImmutable $termination_date
 * @property ?TerminationReason $termination_reason
 * @property string $work_start_time
 * @property string $work_end_time
 * @property list<int> $weekly_off_days
 * @property Money $basic_salary
 * @property ?string $bank_name
 * @property ?string $iban
 * @property ?string $social_insurance_number
 * @property int $version
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read User $user
 * @property-read Person $person
 * @property-read Department $department
 */
class Employee extends Model implements Auditable, OwnedByUser
{
    use AuditsChanges;
    use HasListQuery;
    use HasVersion;

    public $incrementing = false;

    protected $keyType = 'string';

    /** Salary data: visible with employees.view_salary, changed with employees.manage_salary. */
    public const SALARY_FIELDS = ['basic_salary', 'bank_name', 'iban', 'social_insurance_number'];

    /** The id is the user's (set explicitly on hire); status changes go through terminate / changeStatus. */
    protected $guarded = ['id', 'version', 'status', 'termination_date', 'termination_reason'];

    protected $attributes = ['status' => 'active', 'weekly_off_days' => '[5,6]'];

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('employees')
            ->sortable('code', 'last_name', 'first_name', 'job_title', 'hire_date', 'status')
            ->filterable('department_id', ['eq', 'in'], column: 'employees.department_id', resource: 'departments')
            ->filterable('status', ['eq', 'in'], column: 'employees.status', enum: EmployeeStatus::class)
            ->filterable('employment_type', ['eq', 'in'], column: 'employees.employment_type', enum: EmploymentType::class)
            ->filterable('hire_date', ['gte', 'lte', 'between'], column: 'employees.hire_date', type: 'date')
            ->searchIn('persons.first_name', 'persons.last_name', 'persons.code', 'persons.phone', 'employees.job_title')
            ->column('code', 'Code')
            ->column('full_name', 'Name')
            ->column('job_title', 'Job title')
            ->column('department', 'Department')
            ->column('employment_type', 'Contract')
            ->column('hire_date', 'Hired')
            ->column('status', 'Status')
            ->dateFields('hire_date')
            ->defaultSort('last_name,first_name');
    }

    public function isTerminated(): bool
    {
        return $this->status === EmployeeStatus::Terminated;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withDeleted();
    }

    /**
     * @return HasMany<EmployeeSalaryComponent, $this>
     */
    public function salaryComponents(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
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
        return $query->where('employees.id', $user->getAuthIdentifier());
    }

    /** @return list<string> */
    public function auditMasked(): array
    {
        return ['iban'];
    }

    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'status' => EmployeeStatus::class,
            'termination_reason' => TerminationReason::class,
            'hire_date' => 'immutable_date',
            'probation_end_date' => 'immutable_date',
            'termination_date' => 'immutable_date',
            'weekly_off_days' => 'array',
            'basic_salary' => MoneyCast::class,
            'iban' => 'encrypted',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
