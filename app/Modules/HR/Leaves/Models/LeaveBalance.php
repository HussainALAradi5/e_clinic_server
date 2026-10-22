<?php

declare(strict_types=1);

namespace App\Modules\HR\Leaves\Models;

use App\Modules\HR\Employees\Models\Employee;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Days of one leave type for one employee and year. Remaining = entitled + carried forward − used − pending.
 *
 * @property string $id
 * @property string $employee_id
 * @property string $leave_type_id
 * @property int $year
 * @property string $entitled_days
 * @property string $carried_forward_days
 * @property string $used_days
 * @property string $pending_days
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read LeaveType $leaveType
 * @property-read Employee $employee
 */
class LeaveBalance extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $attributes = ['carried_forward_days' => '0', 'used_days' => '0', 'pending_days' => '0'];

    public function remaining(): BigDecimal
    {
        return BigDecimal::of($this->entitled_days)->plus($this->carried_forward_days)->minus($this->used_days)->minus($this->pending_days);
    }

    /** Moves days between the counters (positive adds, negative removes). */
    public function shift(string $counter, BigDecimal|string $days): void
    {
        $this->setAttribute($counter, (string) BigDecimal::of($this->getAttribute($counter))->plus($days)->toScale(1));
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class)->withDeleted();
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'entitled_days' => 'decimal:1',
            'carried_forward_days' => 'decimal:1',
            'used_days' => 'decimal:1',
            'pending_days' => 'decimal:1',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
