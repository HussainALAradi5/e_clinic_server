<?php

declare(strict_types=1);

namespace App\Modules\HR\Payroll\Models;

use App\Enums\HR\PayrollStatus;
use App\Modules\HR\Departments\Models\Department;
use App\Modules\HR\Employees\Models\Employee;
use App\Shared\Casts\MoneyCast;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One employee's pay inside a run (gross − deductions = net, never negative; a shortfall is carried to next month).
 * Staff see their own payslips once the run is approved.
 *
 * @property string $id
 * @property string $payroll_run_id
 * @property string $employee_id
 * @property string $department_id
 * @property Money $basic_salary
 * @property int $working_days
 * @property string $present_days
 * @property string $absent_days
 * @property string $unpaid_leave_days
 * @property string $paid_leave_days
 * @property int $overtime_minutes
 * @property Money $total_allowances
 * @property Money $overtime_amount
 * @property Money $gross_salary
 * @property Money $total_deductions
 * @property Money $net_salary
 * @property Money $carried_forward
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read PayrollRun $run
 * @property-read Employee $employee
 * @property-read Department $department
 * @property-read Collection<int, PayslipItem> $items
 */
class Payslip extends Model implements OwnedByUser
{
    use HasListQuery;
    use HasUuids;

    /** Runs whose payslips the staff member may see. */
    public const VISIBLE_TO_STAFF = [PayrollStatus::Approved, PayrollStatus::Paid];

    protected $guarded = ['id'];

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('payroll')
            ->sortable('net_salary', 'gross_salary', 'created_at')
            ->filterable('payroll_run_id', ['eq'], label: 'Payroll month')
            ->filterable('employee_id', ['eq', 'in'], resource: 'employees')
            ->filterable('department_id', ['eq', 'in'], resource: 'departments')
            ->includes('employee.person', 'department', 'run')
            ->column('employee', 'Employee')
            ->column('department', 'Department')
            ->column('gross_salary', 'Gross')
            ->column('total_deductions', 'Deductions')
            ->column('net_salary', 'Net')
            ->dateFields('created_at')
            ->defaultSort('-created_at');
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withDeleted();
    }

    /**
     * @return HasMany<PayslipItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PayslipItem::class)->orderBy('sort');
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return $this->employee_id === $user->getAuthIdentifier();
    }

    /**
     * Own payslips of approved or paid runs only (a calculation in progress is not shown to staff).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->where('payslips.employee_id', $user->getAuthIdentifier())
            ->whereHas('run', fn (Builder $q) => $q->whereIn('status', array_map(fn (PayrollStatus $s): string => $s->value, self::VISIBLE_TO_STAFF)));
    }

    protected function casts(): array
    {
        return [
            'basic_salary' => MoneyCast::class,
            'working_days' => 'integer',
            'present_days' => 'decimal:1',
            'absent_days' => 'decimal:1',
            'unpaid_leave_days' => 'decimal:1',
            'paid_leave_days' => 'decimal:1',
            'overtime_minutes' => 'integer',
            'total_allowances' => MoneyCast::class,
            'overtime_amount' => MoneyCast::class,
            'gross_salary' => MoneyCast::class,
            'total_deductions' => MoneyCast::class,
            'net_salary' => MoneyCast::class,
            'carried_forward' => MoneyCast::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
