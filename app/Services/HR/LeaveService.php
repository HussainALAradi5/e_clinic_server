<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Dtos\Appointments\ScheduleExceptionData;
use App\Dtos\HR\LeaveRequestData;
use App\Dtos\HR\LeaveTypeData;
use App\Enums\Appointments\ScheduleExceptionType;
use App\Enums\Shared\ApprovalStatus;
use App\Events\HR\LeaveStatusChanged;
use App\Exceptions\HR\EmployeeTerminatedException;
use App\Exceptions\HR\InsufficientLeaveBalanceException;
use App\Exceptions\HR\LeaveAttachmentRequiredException;
use App\Exceptions\HR\LeaveOverlapException;
use App\Exceptions\Shared\InvalidStateTransitionException;
use App\Exceptions\Shared\SelfApprovalException;
use App\Interfaces\Appointments\ScheduleServiceInterface;
use App\Interfaces\HR\AttendanceServiceInterface;
use App\Interfaces\HR\LeaveServiceInterface;
use App\Models\Appointments\ProviderSchedule;
use App\Models\Appointments\ScheduleException;
use App\Models\HR\Employee;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\HR\WorkCalendar;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Leave types, yearly balances and requests through the shared approval workflow. Approved leave marks attendance
 * and blocks the provider's bookable days; unpaid leave is deducted by payroll.
 */
final class LeaveService implements LeaveServiceInterface
{
    public const RELATIONS = ['employee.person', 'employee.department', 'leaveType', 'reviewer.person'];

    public function __construct(
        private readonly ApplyListQuery $listQuery,
        private readonly ClinicClock $clock,
        private readonly WorkCalendar $calendar,
        private readonly AttendanceServiceInterface $attendance,
        private readonly ScheduleServiceInterface $schedules,
    ) {}

    public function listTypes(bool $withArchived = false): Collection
    {
        return LeaveType::query()->when(! $withArchived, fn (Builder $q) => $q->where('is_archived', false))->orderBy('name')->get();
    }

    public function saveType(LeaveTypeData $data, ?LeaveType $type = null): LeaveType
    {
        return DB::transaction(function () use ($data, $type): LeaveType {
            $type ??= new LeaveType;
            $type->fill($data->values)->save();

            return $type->refresh();
        });
    }

    public function balances(Employee $employee, int $year): Collection
    {
        $this->initializeBalances($employee, $year);

        return LeaveBalance::query()->with('leaveType')->where('employee_id', $employee->id)->where('year', $year)->get()
            ->filter(fn (LeaveBalance $b): bool => ! $b->leaveType->is_deleted)
            ->sortBy(fn (LeaveBalance $b): string => $b->leaveType->name)
            ->values();
    }

    public function initializeBalances(Employee $employee, int $year): void
    {
        $existing = LeaveBalance::query()->where('employee_id', $employee->id)->where('year', $year)->pluck('leave_type_id')->flip();
        foreach ($this->listTypes() as $type) {
            if ($existing->has($type->id)) {
                continue;
            }
            LeaveBalance::query()->firstOrCreate(
                ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
                ['entitled_days' => (string) $this->entitlement($employee, $type, $year), 'carried_forward_days' => (string) $this->carryForward($employee, $type, $year)],
            );
        }
    }

    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->query(), $query);
    }

    public function find(string $id): LeaveRequest
    {
        return $this->query()->with('attachments')->findOrFail($id);
    }

    public function request(Employee $employee, LeaveRequestData $data): LeaveRequest
    {
        if ($employee->isTerminated()) {
            throw new EmployeeTerminatedException;
        }
        $type = LeaveType::query()->where('is_archived', false)->findOrFail($data->leaveTypeId);
        $days = $this->countDays($employee, $data->startDate, $data->endDate, $data->isHalfDay);
        $today = $this->clock->today();
        // Notice applies to the employee's own requests; HR may record leave after the fact.
        if ($type->needsNotice() && $employee->id === Auth::id() && $data->startDate->toDateString() < $today->addDays($type->min_notice_days)->toDateString()) {
            throw ValidationException::withMessages(['start_date' => [__('errors.LEAVE_NOTICE', ['type' => $type->name, 'days' => $type->min_notice_days])]]);
        }

        $request = DB::transaction(function () use ($employee, $type, $data, $days): LeaveRequest {
            $this->assertNoOverlap($employee, $data->startDate, $data->endDate);
            $request = new LeaveRequest([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $data->startDate->toDateString(),
                'end_date' => $data->endDate->toDateString(),
                'total_days' => (string) $days,
                'is_half_day' => $data->isHalfDay,
                'reason' => $data->reason,
            ]);
            $request->status = $type->requires_attachment ? ApprovalStatus::Draft : ApprovalStatus::Pending;
            if ($request->status === ApprovalStatus::Pending) {
                $this->hold($employee, $type, $data->startDate->year, $days);
            } else {
                $this->assertBalance($this->balance($employee, $type, $data->startDate->year), $type, $days);
            }
            $request->save();

            return $request;
        });
        if ($request->status === ApprovalStatus::Pending) {
            LeaveStatusChanged::dispatch($request);
        }

        return $this->find($request->id);
    }

    public function submit(LeaveRequest $request): LeaveRequest
    {
        $request = DB::transaction(function () use ($request): LeaveRequest {
            $request = $this->locked($request, ApprovalStatus::Draft, ApprovalStatus::Pending);
            if ($request->leaveType->requires_attachment && ! $request->attachments()->where('is_deleted', false)->exists()) {
                throw new LeaveAttachmentRequiredException(__('errors.LEAVE_ATTACHMENT_REQUIRED', ['type' => $request->leaveType->name]));
            }
            $this->hold($request->employee, $request->leaveType, $request->start_date->year, BigDecimal::of($request->total_days));
            $request->forceFill(['status' => ApprovalStatus::Pending])->save();

            return $request;
        });
        LeaveStatusChanged::dispatch($request);

        return $this->find($request->id);
    }

    public function approve(LeaveRequest $request, ?string $notes = null, bool $cancelAffected = false): LeaveRequest
    {
        $request = DB::transaction(function () use ($request, $notes, $cancelAffected): LeaveRequest {
            $request = $this->decide($request, ApprovalStatus::Approved, $notes);
            $balance = $this->balance($request->employee, $request->leaveType, $request->start_date->year, lock: true);
            $balance->shift('pending_days', BigDecimal::of($request->total_days)->negated());
            $balance->shift('used_days', $request->total_days);
            $balance->save();

            $days = $this->calendar->workingDays($request->employee, $request->start_date, $request->end_date);
            $this->attendance->markLeave($request->employee, $days);
            $this->blockProviderDays($request, $days, $cancelAffected);

            return $request;
        });
        LeaveStatusChanged::dispatch($request);

        return $this->find($request->id);
    }

    public function reject(LeaveRequest $request, string $notes): LeaveRequest
    {
        $request = DB::transaction(function () use ($request, $notes): LeaveRequest {
            $request = $this->decide($request, ApprovalStatus::Rejected, $notes);
            $this->release($request, 'pending_days');

            return $request;
        });
        LeaveStatusChanged::dispatch($request);

        return $this->find($request->id);
    }

    public function cancel(LeaveRequest $request): LeaveRequest
    {
        $request = DB::transaction(function () use ($request): LeaveRequest {
            $request = LeaveRequest::query()->with(['employee', 'leaveType'])->lockForUpdate()->findOrFail($request->id);
            $from = $request->status;
            $allowed = match ($from) {
                ApprovalStatus::Draft, ApprovalStatus::Pending => true,
                ApprovalStatus::Approved => Auth::user()?->can('leaves.approve') === true && ! $request->hasStarted($this->clock->today()),
                default => false,
            };
            if (! $allowed) {
                throw new InvalidStateTransitionException(context: ['from' => $from->value, 'to' => ApprovalStatus::Cancelled->value]);
            }
            if ($from === ApprovalStatus::Pending) {
                $this->release($request, 'pending_days');
            }
            if ($from === ApprovalStatus::Approved) {
                $this->release($request, 'used_days');
                $days = $this->calendar->workingDays($request->employee, $request->start_date, $request->end_date);
                $this->attendance->clearLeave($request->employee, $days);
                $this->unblockProviderDays($request);
            }
            $request->forceFill(['status' => ApprovalStatus::Cancelled])->save();

            return $request;
        });
        LeaveStatusChanged::dispatch($request);

        return $this->find($request->id);
    }

    /**
     * @return Builder<LeaveRequest>
     */
    private function query(): Builder
    {
        return OwnershipScope::apply(LeaveRequest::query()->with(self::RELATIONS), 'leaves', Auth::user());
    }

    /** Working days of the request (0.5 for a half day); none → validation error. */
    private function countDays(Employee $employee, CarbonImmutable $start, CarbonImmutable $end, bool $halfDay): BigDecimal
    {
        $working = count($this->calendar->workingDays($employee, $start, $end));
        if ($working === 0) {
            throw ValidationException::withMessages(['start_date' => [__('errors.LEAVE_NO_WORKING_DAYS')]]);
        }

        return $halfDay ? BigDecimal::of('0.5') : BigDecimal::of($working)->toScale(1);
    }

    private function assertNoOverlap(Employee $employee, CarbonImmutable $start, CarbonImmutable $end, ?string $except = null): void
    {
        $clash = LeaveRequest::query()->where('employee_id', $employee->id)
            ->whereIn('status', array_map(fn (ApprovalStatus $s): string => $s->value, [ApprovalStatus::Draft, ...LeaveRequest::ACTIVE]))
            ->whereDate('start_date', '<=', $end->toDateString())->whereDate('end_date', '>=', $start->toDateString())
            ->when($except !== null, fn (Builder $q) => $q->whereKeyNot($except))
            ->lockForUpdate()->first();
        if ($clash !== null) {
            throw new LeaveOverlapException(__('errors.LEAVE_OVERLAP', ['code' => $clash->code]), ['code' => $clash->code]);
        }
    }

    /** Reserves the days on the balance (pending), checking what is left on limited types. */
    private function hold(Employee $employee, LeaveType $type, int $year, BigDecimal $days): void
    {
        $balance = $this->balance($employee, $type, $year, lock: true);
        $this->assertBalance($balance, $type, $days);
        $balance->shift('pending_days', $days);
        $balance->save();
    }

    private function assertBalance(LeaveBalance $balance, LeaveType $type, BigDecimal $days): void
    {
        if ($type->isLimited() && $balance->remaining()->isLessThan($days)) {
            $remaining = (string) $balance->remaining()->toScale(1);
            throw new InsufficientLeaveBalanceException(__('errors.LEAVE_INSUFFICIENT_BALANCE', ['remaining' => $remaining, 'type' => $type->name, 'days' => (string) $days]), ['remaining' => $remaining]);
        }
    }

    /** Gives the request's days back from a counter (pending_days / used_days). */
    private function release(LeaveRequest $request, string $counter): void
    {
        $balance = $this->balance($request->employee, $request->leaveType, $request->start_date->year, lock: true);
        $balance->shift($counter, BigDecimal::of($request->total_days)->negated());
        $balance->save();
    }

    private function balance(Employee $employee, LeaveType $type, int $year, bool $lock = false): LeaveBalance
    {
        $this->initializeBalances($employee, $year);
        $query = LeaveBalance::query()->where('employee_id', $employee->id)->where('leave_type_id', $type->id)->where('year', $year);

        return ($lock ? $query->lockForUpdate() : $query)->firstOr(fn (): LeaveBalance => LeaveBalance::query()->create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year, 'entitled_days' => '0',
        ]));
    }

    /** Pending → approved / rejected by someone other than the requester. */
    private function decide(LeaveRequest $request, ApprovalStatus $to, ?string $notes): LeaveRequest
    {
        $request = $this->locked($request, ApprovalStatus::Pending, $to);
        if ($request->employee_id === Auth::id()) {
            throw new SelfApprovalException;
        }
        $request->forceFill(['status' => $to, 'reviewed_by' => Auth::id(), 'reviewed_at' => $this->clock->now(), 'review_notes' => $notes])->save();

        return $request;
    }

    private function locked(LeaveRequest $request, ApprovalStatus $from, ApprovalStatus $to): LeaveRequest
    {
        $request = LeaveRequest::query()->with(['employee', 'leaveType'])->lockForUpdate()->findOrFail($request->id);
        if ($request->status !== $from) {
            throw new InvalidStateTransitionException(context: ['from' => $request->status->value, 'to' => $to->value]);
        }

        return $request;
    }

    /**
     * Doctors and therapists on leave are not bookable: a day off per working day (bookings on those days are listed
     * unless HR chose to cancel them).
     *
     * @param  list<CarbonImmutable>  $days
     */
    private function blockProviderDays(LeaveRequest $request, array $days, bool $cancelAffected): void
    {
        if (! ProviderSchedule::query()->where('provider_id', $request->employee_id)->exists()) {
            return;
        }
        $taken = ScheduleException::query()->where('provider_id', $request->employee_id)
            ->whereDate('date', '>=', $request->start_date->toDateString())->whereDate('date', '<=', $request->end_date->toDateString())
            ->pluck('date')->map(fn (CarbonImmutable $d): string => $d->toDateString())->flip();
        foreach ($days as $day) {
            if (! $taken->has($day->toDateString())) {
                $this->schedules->addException($request->employee, new ScheduleExceptionData($day, ScheduleExceptionType::DayOff, reason: $this->dayOffReason($request), cancelAffected: $cancelAffected));
            }
        }
    }

    private function unblockProviderDays(LeaveRequest $request): void
    {
        ScheduleException::query()->where('provider_id', $request->employee_id)->where('reason', $this->dayOffReason($request))
            ->get()->each(fn (ScheduleException $e) => $this->schedules->removeException($e));
    }

    private function dayOffReason(LeaveRequest $request): string
    {
        return __('Leave :code', ['code' => $request->code]);
    }

    /** Yearly quota; joiners get the months left including their hire month, rounded to half days. */
    private function entitlement(Employee $employee, LeaveType $type, int $year): BigDecimal
    {
        $quota = BigDecimal::of($type->annual_quota_days);
        if (! $type->is_paid || $employee->hire_date->year > $year) {
            return BigDecimal::zero()->toScale(1);
        }
        if ($employee->hire_date->year === $year) {
            $quota = $quota->multipliedBy(13 - $employee->hire_date->month)->dividedBy(12, 6, RoundingMode::HALF_UP);
        }

        return $quota->multipliedBy(2)->toScale(0, RoundingMode::HALF_UP)->dividedBy(2, 1);
    }

    private function carryForward(Employee $employee, LeaveType $type, int $year): BigDecimal
    {
        $previous = LeaveBalance::query()->where('employee_id', $employee->id)->where('leave_type_id', $type->id)->where('year', $year - 1)->first();
        if ($previous === null || ! $type->isLimited()) {
            return BigDecimal::zero()->toScale(1);
        }
        $left = $previous->remaining();
        $max = BigDecimal::of($type->max_carry_forward_days);

        return ($left->isGreaterThan($max) ? $max : ($left->isNegative() ? BigDecimal::zero() : $left))->toScale(1);
    }
}
