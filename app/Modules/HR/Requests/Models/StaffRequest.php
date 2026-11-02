<?php

declare(strict_types=1);

namespace App\Modules\HR\Requests\Models;

use App\Enums\HR\StaffRequestType;
use App\Modules\HR\Attendance\Models\Attendance;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Enums\ApprovalStatus;
use App\Shared\Models\BaseModel;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A staff request (REQ-2026-000001) about one attendance day: excuse a late arrival or pay the overtime. Pending →
 * approved / rejected by HR (never by the requester), with HR's reason; the requester may cancel while pending.
 * `details` keeps the minutes (and the overtime amount) as they were when it was asked.
 *
 * @property string $id
 * @property string $code
 * @property StaffRequestType $type
 * @property string $employee_id
 * @property string $attendance_id
 * @property ?string $reason
 * @property ApprovalStatus $status
 * @property array<string, mixed>|null $details
 * @property ?string $reviewed_by
 * @property ?CarbonImmutable $reviewed_at
 * @property ?string $review_reason
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Employee $employee
 * @property-read Attendance $attendance
 * @property-read ?User $reviewer
 */
class StaffRequest extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    /** A day has at most one of these per type (a rejected one is final; a cancelled one may be asked again). */
    public const HOLDING = [ApprovalStatus::Pending, ApprovalStatus::Approved, ApprovalStatus::Rejected];

    public static function codePrefix(): string
    {
        return 'REQ';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('staff_requests')
            ->sortable('created_at', 'code', 'status', 'type', 'reviewed_at')
            ->filterable('type', ['eq', 'in'], enum: StaffRequestType::class)
            ->filterable('status', ['eq', 'in', 'nin'], enum: ApprovalStatus::class)
            ->filterable('employee_id', ['eq', 'in'], resource: 'employees')
            ->filterable('created_at', ['gte', 'lte', 'between'], type: 'date')
            ->searchIn('code', 'reason')
            ->includes('employee.person', 'employee.department', 'attendance', 'reviewer.person')
            ->column('code', 'Code')
            ->column('employee', 'Employee')
            ->column('type', 'Request')
            ->column('date', 'Day')
            ->column('minutes', 'Minutes')
            ->column('status', 'Status')
            ->column('created_at', 'Asked')
            ->dateFields('created_at', 'reviewed_at')
            ->defaultSort('-created_at');
    }

    public function minutes(): int
    {
        return (int) ($this->details['minutes'] ?? 0);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Attendance, $this>
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
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
        return $query->where('staff_requests.employee_id', $user->getAuthIdentifier());
    }

    protected function casts(): array
    {
        return [
            'type' => StaffRequestType::class,
            'status' => ApprovalStatus::class,
            'details' => 'array',
            'reviewed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
