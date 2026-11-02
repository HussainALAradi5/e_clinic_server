<?php

declare(strict_types=1);

namespace App\Modules\Appointments\SickLeaves\Services;

use App\Enums\Appointments\AppointmentStatus;
use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\SickLeaves\Models\SickLeave;
use App\Shared\Access\OwnershipScope;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Query\ListQuery;
use App\Shared\Support\ClinicClock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Sick leave certificates: the doctor of a visit issues one for its patient (from the visit day, or up to 3 days
 * before it for an illness that started earlier; at most 30 days); anyone who may see it lists and prints it; the
 * issuing doctor cancels it with a reason (it stays on file).
 */
final class SickLeaveService
{
    /** How far back a certificate may start before the visit, and its longest length. */
    public const MAX_BACKDATE_DAYS = 3;

    public const MAX_DAYS = 30;

    private const RELATIONS = ['patient.person', 'doctor.person', 'appointment'];

    public function __construct(private readonly ApplyListQuery $listQuery, private readonly ClinicClock $clock) {}

    /** @return LengthAwarePaginator<int, SickLeave>|CursorPaginator<int, SickLeave> */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->query(), $query);
    }

    public function find(string $id): SickLeave
    {
        return $this->query()->findOrFail($id);
    }

    /** @param  array{start_date: string, end_date: string, reason?: ?string, show_reason?: bool, notes?: ?string}  $data */
    public function issue(Appointment $visit, string $doctorId, array $data): SickLeave
    {
        if ($visit->provider_id !== $doctorId) {
            throw ValidationException::withMessages(['appointment' => [__('errors.SICK_LEAVE_NOT_YOUR_VISIT')]]);
        }
        if (! in_array($visit->status, [AppointmentStatus::CheckedIn, AppointmentStatus::InProgress, AppointmentStatus::Completed], true)) {
            throw new InvalidStateTransitionException(__('errors.SICK_LEAVE_NEEDS_VISIT'), ['status' => $visit->status->value]);
        }
        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);
        $visitDay = CarbonImmutable::parse($visit->scheduled_date->toDateString());
        $days = (int) $start->diffInDays($end) + 1;
        if ($start->lt($visitDay->subDays(self::MAX_BACKDATE_DAYS)) || $start->gt($visitDay->addDays(1))) {
            throw ValidationException::withMessages(['start_date' => [__('errors.SICK_LEAVE_START', ['days' => self::MAX_BACKDATE_DAYS])]]);
        }
        if ($days > self::MAX_DAYS) {
            throw ValidationException::withMessages(['end_date' => [__('errors.SICK_LEAVE_TOO_LONG', ['days' => self::MAX_DAYS])]]);
        }

        $leave = SickLeave::query()->create([
            'patient_id' => $visit->patient_id,
            'doctor_id' => $doctorId,
            'appointment_id' => $visit->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'reason' => $data['reason'] ?? null,
            'show_reason' => (bool) ($data['show_reason'] ?? false),
            'notes' => $data['notes'] ?? null,
            'issued_at' => $this->clock->now(),
        ]);

        return $this->find($leave->id);
    }

    /** Only the doctor who issued it; it stays on file, marked cancelled. */
    public function cancel(SickLeave $leave, string $userId, string $reason): SickLeave
    {
        if ($leave->doctor_id !== $userId) {
            throw ValidationException::withMessages(['sick_leave' => [__('errors.SICK_LEAVE_NOT_YOURS')]]);
        }
        if ($leave->isCancelled()) {
            throw new InvalidStateTransitionException(context: ['from' => 'cancelled', 'to' => 'cancelled']);
        }
        $leave->forceFill(['cancelled_at' => $this->clock->now(), 'cancel_reason' => $reason])->save();

        return $this->find($leave->id);
    }

    /**
     * @return Builder<SickLeave>
     */
    private function query(): Builder
    {
        return OwnershipScope::apply(SickLeave::query()->with(self::RELATIONS), 'sick_leaves', Auth::user());
    }
}
