<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Enums\HR\StaffRequestType;
use App\Enums\Shared\ApprovalStatus;
use App\Events\HR\StaffRequestChanged;
use App\Exceptions\HR\NothingToRequestException;
use App\Exceptions\HR\NotYourRequestException;
use App\Exceptions\HR\StaffRequestDisabledException;
use App\Exceptions\HR\StaffRequestExistsException;
use App\Exceptions\Shared\InvalidStateTransitionException;
use App\Exceptions\Shared\SelfApprovalException;
use App\Handlers\HR\LateExcuseHandler;
use App\Handlers\HR\OvertimeHandler;
use App\Interfaces\HR\AttendanceServiceInterface;
use App\Interfaces\HR\SettingServiceInterface;
use App\Interfaces\HR\StaffRequestHandler;
use App\Interfaces\HR\StaffRequestServiceInterface;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\StaffRequest;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class StaffRequestService implements StaffRequestServiceInterface
{
    public const RELATIONS = ['employee.person', 'employee.department', 'attendance', 'reviewer.person'];

    /** @var array<string, StaffRequestHandler> */
    private array $handlers;

    public function __construct(
        private readonly ApplyListQuery $listQuery,
        private readonly ClinicClock $clock,
        private readonly AttendanceServiceInterface $attendance,
        private readonly SettingServiceInterface $settings,
        LateExcuseHandler $lateExcuse,
        OvertimeHandler $overtime,
    ) {
        foreach ([$lateExcuse, $overtime] as $handler) {
            $this->handlers[$handler->type()->value] = $handler;
        }
    }

    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->query(), $query);
    }

    public function find(string $id): StaffRequest
    {
        return $this->query()->findOrFail($id);
    }

    public function askable(Employee $employee): array
    {
        $types = array_values(array_filter(StaffRequestType::cases(), fn (StaffRequestType $t): bool => $this->settings->enabled($t->setting())));
        if ($types === []) {
            return [];
        }
        $days = Attendance::query()->with('employee')->where('employee_id', $employee->id)->where('is_locked', false)
            ->whereDate('date', '>=', $this->clock->today()->subDays(31)->toDateString())
            ->where(fn (Builder $q) => $q->where('late_minutes', '>', 0)->orWhere('overtime_minutes', '>', 0))
            ->orderByDesc('date')->get();
        $taken = StaffRequest::query()->whereIn('attendance_id', $days->modelKeys())
            ->whereIn('status', array_map(fn (ApprovalStatus $s): string => $s->value, StaffRequest::HOLDING))
            ->get(['attendance_id', 'type'])->map(fn (StaffRequest $r): string => "{$r->attendance_id}:{$r->type->value}")->flip();

        $askable = [];
        foreach ($days as $day) {
            $open = array_values(array_filter($types, fn (StaffRequestType $t): bool => ! $taken->has("{$day->id}:{$t->value}") && $this->canAsk($this->handler($t), $day)));
            if ($open !== []) {
                $askable[] = ['attendance' => $day, 'types' => $open];
            }
        }

        return $askable;
    }

    public function create(Employee $employee, StaffRequestType $type, string $attendanceId, ?string $reason): StaffRequest
    {
        if (! $this->settings->enabled($type->setting())) {
            throw new StaffRequestDisabledException;
        }
        $request = DB::transaction(function () use ($employee, $type, $attendanceId, $reason): StaffRequest {
            $day = Attendance::query()->with('employee')->where('employee_id', $employee->id)->lockForUpdate()->findOrFail($attendanceId);
            $this->attendance->assertEditable($day);
            $handler = $this->handler($type);
            if (! $this->canAsk($handler, $day)) {
                throw new NothingToRequestException;
            }
            $exists = StaffRequest::query()->where('attendance_id', $day->id)->where('type', $type->value)
                ->whereIn('status', array_map(fn (ApprovalStatus $s): string => $s->value, StaffRequest::HOLDING))->exists();
            if ($exists) {
                throw new StaffRequestExistsException;
            }

            return StaffRequest::query()->create([
                'type' => $type, 'employee_id' => $employee->id, 'attendance_id' => $day->id, 'reason' => $reason,
                'status' => ApprovalStatus::Pending, 'details' => $handler->details($day),
            ]);
        });
        StaffRequestChanged::dispatch($request);

        return $this->find($request->id);
    }

    public function approve(StaffRequest $request, ?string $reason = null): StaffRequest
    {
        $request = DB::transaction(function () use ($request, $reason): StaffRequest {
            $request = $this->decide($request, ApprovalStatus::Approved, $reason);
            $handler = $this->handler($request->type);
            $day = $handler->approve($request->attendance);
            $request->forceFill(['details' => $handler->details($day->loadMissing('employee'))])->save();

            return $request;
        });
        StaffRequestChanged::dispatch($request);

        return $this->find($request->id);
    }

    public function reject(StaffRequest $request, string $reason): StaffRequest
    {
        $request = DB::transaction(fn (): StaffRequest => $this->decide($request, ApprovalStatus::Rejected, $reason));
        StaffRequestChanged::dispatch($request);

        return $this->find($request->id);
    }

    public function cancel(StaffRequest $request): StaffRequest
    {
        $request = DB::transaction(function () use ($request): StaffRequest {
            $request = $this->locked($request, ApprovalStatus::Cancelled);
            if ($request->employee_id !== Auth::id()) {
                throw new NotYourRequestException;
            }
            $request->forceFill(['status' => ApprovalStatus::Cancelled])->save();

            return $request;
        });
        StaffRequestChanged::dispatch($request);

        return $this->find($request->id);
    }

    /** Pending → approved / rejected by someone other than the requester, with the reviewer's reason. */
    private function decide(StaffRequest $request, ApprovalStatus $to, ?string $reason): StaffRequest
    {
        $request = $this->locked($request, $to);
        if ($request->employee_id === Auth::id()) {
            throw new SelfApprovalException;
        }
        $this->attendance->assertEditable($request->attendance);
        $request->forceFill(['status' => $to, 'reviewed_by' => Auth::id(), 'reviewed_at' => $this->clock->now(), 'review_reason' => $reason])->save();

        return $request;
    }

    private function locked(StaffRequest $request, ApprovalStatus $to): StaffRequest
    {
        $request = StaffRequest::query()->with(['employee', 'attendance.employee'])->lockForUpdate()->findOrFail($request->id);
        if ($request->status !== ApprovalStatus::Pending) {
            throw new InvalidStateTransitionException(context: ['from' => $request->status->value, 'to' => $to->value]);
        }

        return $request;
    }

    private function canAsk(StaffRequestHandler $handler, Attendance $day): bool
    {
        return $handler->minutes($day) > 0 && ! $handler->isSettled($day);
    }

    private function handler(StaffRequestType $type): StaffRequestHandler
    {
        return $this->handlers[$type->value];
    }

    /**
     * @return Builder<StaffRequest>
     */
    private function query(): Builder
    {
        return OwnershipScope::apply(StaffRequest::query()->with(self::RELATIONS), 'staff_requests', Auth::user());
    }
}
