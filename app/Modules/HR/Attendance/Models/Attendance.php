<?php

declare(strict_types=1);

namespace App\Modules\HR\Attendance\Models;

use App\Enums\HR\AttendanceSource;
use App\Enums\HR\AttendanceStatus;
use App\Modules\HR\Employees\Models\Employee;
use App\Shared\Casts\MoneyCast;
use App\Shared\Contracts\Auditable;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\Auditable as AuditsChanges;
use App\Shared\Traits\Blameable;
use App\Shared\Traits\HasListQuery;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's working day: check-in / check-out, lateness, overtime (paid only once approved) and the day result.
 * Locked once the month is in an approved payroll.
 *
 * @property string $id
 * @property string $employee_id
 * @property CarbonImmutable $date
 * @property ?CarbonImmutable $check_in_at
 * @property ?CarbonImmutable $check_out_at
 * @property int $worked_minutes
 * @property int $late_minutes
 * @property int $overtime_minutes
 * @property bool $overtime_approved
 * @property ?Money $overtime_amount
 * @property bool $late_excused
 * @property AttendanceStatus $status
 * @property AttendanceSource $source
 * @property ?string $check_in_ip
 * @property ?string $notes
 * @property bool $needs_review
 * @property bool $is_locked
 * @property ?string $created_by
 * @property ?string $updated_by
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Employee $employee
 */
class Attendance extends Model implements Auditable, OwnedByUser
{
    use AuditsChanges;
    use Blameable;
    use HasListQuery;
    use HasUuids;

    protected $guarded = ['id', 'is_locked'];

    protected $attributes = ['worked_minutes' => 0, 'late_minutes' => 0, 'overtime_minutes' => 0, 'overtime_approved' => false, 'late_excused' => false, 'needs_review' => false, 'is_locked' => false];

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('attendance')
            ->sortable('date', 'status', 'late_minutes', 'overtime_minutes', 'check_in_at')
            ->filterable('employee_id', ['eq', 'in'], resource: 'employees')
            ->filterable('status', ['eq', 'in', 'nin'], enum: AttendanceStatus::class)
            ->filterable('source', ['eq'], enum: AttendanceSource::class)
            ->filterable('overtime_approved', ['eq'], type: 'boolean')
            ->filterable('late_excused', ['eq'], type: 'boolean')
            ->filterable('needs_review', ['eq'], type: 'boolean')
            ->filterable('overtime_minutes', ['gt', 'gte'], type: 'number')
            ->filterable('late_minutes', ['gt', 'gte'], type: 'number')
            ->includes('employee.person', 'employee.department', 'employee.shift')
            ->column('date', 'Date')
            ->column('employee', 'Employee')
            ->column('shift', 'Shift')
            ->column('check_in_at', 'In')
            ->column('check_out_at', 'Out')
            ->column('worked_minutes', 'Worked (min)')
            ->column('late_minutes', 'Late (min)')
            ->column('overtime_minutes', 'Overtime (min)')
            ->column('status', 'Status')
            ->dateFields('date')
            ->defaultSort('-date');
    }

    /** Overtime waiting for a manager. */
    public function hasPendingOvertime(): bool
    {
        return $this->overtime_minutes > 0 && ! $this->overtime_approved;
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isOwnedBy(Authenticatable $user): bool
    {
        return $this->employee_id === $user->getAuthIdentifier();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): Builder
    {
        return $query->where('attendances.employee_id', $user->getAuthIdentifier());
    }

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'check_in_at' => 'immutable_datetime',
            'check_out_at' => 'immutable_datetime',
            'worked_minutes' => 'integer',
            'late_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'overtime_approved' => 'boolean',
            'overtime_amount' => MoneyCast::class,
            'late_excused' => 'boolean',
            'status' => AttendanceStatus::class,
            'source' => AttendanceSource::class,
            'needs_review' => 'boolean',
            'is_locked' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
