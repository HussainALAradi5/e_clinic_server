<?php

declare(strict_types=1);

namespace App\Modules\HR\Leaves\Models;

use App\Modules\HR\Employees\Models\Employee;
use App\Modules\Identity\Attachments\Models\Attachment;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Contracts\OwnedByUser;
use App\Shared\Enums\ApprovalStatus;
use App\Shared\Models\BaseModel;
use App\Shared\Query\ListQueryConfig;
use App\Shared\Traits\HasListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Leave request through the shared approval workflow: draft (waiting for its certificate) → pending → approved /
 * rejected; cancelled by the requester while pending, or before it starts once approved.
 *
 * @property string $id
 * @property string $code
 * @property string $employee_id
 * @property string $leave_type_id
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property string $total_days
 * @property bool $is_half_day
 * @property ?string $reason
 * @property ApprovalStatus $status
 * @property ?string $reviewed_by
 * @property ?CarbonImmutable $reviewed_at
 * @property ?string $review_notes
 * @property ?string $created_by
 * @property bool $is_archived
 * @property bool $is_deleted
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read Employee $employee
 * @property-read LeaveType $leaveType
 * @property-read ?User $reviewer
 * @property-read Collection<int, Attachment> $attachments
 */
class LeaveRequest extends BaseModel implements OwnedByUser
{
    use HasListQuery;

    /** Statuses that hold the days (overlap and balance). */
    public const ACTIVE = [ApprovalStatus::Pending, ApprovalStatus::Approved];

    public static function codePrefix(): string
    {
        return 'LV';
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('leaves')
            ->sortable('start_date', 'end_date', 'code', 'status', 'total_days', 'created_at')
            ->filterable('status', ['eq', 'in', 'nin'], enum: ApprovalStatus::class)
            ->filterable('employee_id', ['eq', 'in'], resource: 'employees')
            ->filterable('leave_type_id', ['eq', 'in'])
            ->filterable('start_date', ['gte', 'lte', 'between'], type: 'date')
            ->filterable('end_date', ['gte', 'lte', 'between'], type: 'date')
            ->searchIn('code', 'reason')
            ->searchInRelation('employee.person', 'first_name', 'last_name', 'code')
            ->includes('employee.person', 'leaveType', 'reviewer.person')
            ->column('code', 'Code')
            ->column('employee', 'Employee')
            ->column('leave_type', 'Type')
            ->column('start_date', 'From')
            ->column('end_date', 'To')
            ->column('total_days', 'Days')
            ->column('status', 'Status')
            ->dateFields('start_date', 'end_date', 'created_at')
            ->defaultSort('-start_date');
    }

    public function hasStarted(CarbonImmutable $today): bool
    {
        return $this->start_date->lte($today);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class)->withDeleted();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
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
        return $query->where('leave_requests.employee_id', $user->getAuthIdentifier());
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'total_days' => 'decimal:1',
            'is_half_day' => 'boolean',
            'status' => ApprovalStatus::class,
            'reviewed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
