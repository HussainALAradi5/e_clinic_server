<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Treatments\Services;

use App\Enums\Appointments\AppointmentSource;
use App\Enums\Appointments\AppointmentStatus;
use App\Enums\Appointments\AppointmentType;
use App\Enums\Appointments\CancelledBy;
use App\Enums\Appointments\TreatmentPriority;
use App\Enums\Identity\AuditAction;
use App\Modules\Appointments\Appointments\Contracts\AppointmentServiceInterface;
use App\Modules\Appointments\Appointments\Data\BookAppointmentData;
use App\Modules\Appointments\Appointments\Data\CancelAppointmentData;
use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Treatments\Contracts\TreatmentServiceInterface;
use App\Modules\Appointments\Treatments\Data\SessionBookingData;
use App\Modules\Appointments\Treatments\Data\TreatmentData;
use App\Modules\Appointments\Treatments\Data\TreatmentProgress;
use App\Modules\Appointments\Treatments\Events\TreatmentAttendanceAlert;
use App\Modules\Appointments\Treatments\Events\TreatmentCancelled;
use App\Modules\Appointments\Treatments\Events\TreatmentCompleted;
use App\Modules\Appointments\Treatments\Events\TreatmentCreated;
use App\Modules\Appointments\Treatments\Events\TreatmentPriorityChanged;
use App\Modules\Appointments\Treatments\Exceptions\PriorityDeadlineException;
use App\Modules\Appointments\Treatments\Exceptions\SessionLimitReachedException;
use App\Modules\Appointments\Treatments\Exceptions\TreatmentNotOpenException;
use App\Modules\Appointments\Treatments\Models\Treatment;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Access\OwnershipScope;
use App\Shared\Contracts\AuditRecorder;
use App\Shared\Enums\GenericStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Query\ListQuery;
use App\Shared\Services\BaseEntityService;
use App\Shared\Support\ClinicClock;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Treatment plans and their sessions. Sessions are booked through the appointment engine (same slot, room and
 * overlap rules); progress is always computed from them.
 *
 * @extends BaseEntityService<Treatment>
 */
final class TreatmentService extends BaseEntityService implements TreatmentServiceInterface
{
    public const RELATIONS = ['patient.person', 'doctor.person', 'doctor.specialty', 'billableService', 'appointment'];

    /** Sessions still to happen (cancelled when the plan ends). */
    private const BOOKED = [AppointmentStatus::Pending, AppointmentStatus::Confirmed];

    public function __construct(
        ApplyListQuery $listQuery,
        private readonly AppointmentServiceInterface $appointments,
        private readonly AuditRecorder $audit,
        private readonly ClinicClock $clock,
    ) {
        parent::__construct($listQuery);
    }

    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->query()->with('sessions'), $query);
    }

    public function find(string $id): Treatment
    {
        return $this->query()->with('sessions')->findOrFail($id);
    }

    public function findWithDeleted(string $id): Treatment
    {
        return parent::findWithDeleted($id);
    }

    public function progress(Treatment $treatment): TreatmentProgress
    {
        return TreatmentProgress::of($treatment->planned_sessions, $treatment->sessions, $this->clock->now());
    }

    public function sessions(Treatment $treatment): Collection
    {
        return $treatment->sessions()
            ->where('status', '!=', AppointmentStatus::Rescheduled->value)
            ->with(['provider.person', 'room'])
            ->withExists('medicalRecord as has_note')
            ->get()
            ->toBase();
    }

    public function bookSession(Treatment $treatment, SessionBookingData $data): Appointment
    {
        return DB::transaction(function () use ($treatment, $data): Appointment {
            // One booking at a time per plan, so two desks never take the same session number.
            $treatment = Treatment::query()->lockForUpdate()->findOrFail($treatment->id);
            if (! $treatment->isOpen()) {
                throw new TreatmentNotOpenException;
            }

            $taken = $treatment->sessions()->whereIn('status', AppointmentStatus::slotHoldingValues())->pluck('session_number')->map(fn (mixed $n): int => (int) $n);
            $number = collect(range(1, $treatment->planned_sessions))->first(fn (int $n): bool => ! $taken->contains($n));
            if ($number === null) {
                throw new SessionLimitReachedException;
            }

            $start = CarbonImmutable::parse($data->date->toDateString().' '.$data->startTime, $this->clock->timezone());
            if ($taken->isEmpty() && ! $treatment->sessions()->where('is_attended', true)->exists()) {
                $this->assertFirstSessionDeadline($treatment, $start, $data->overrideDeadline);
            }

            return $this->appointments->bookSession(new BookAppointmentData(
                patientId: $treatment->patient_id,
                providerId: $data->providerId,
                type: AppointmentType::TreatmentSession,
                date: $data->date,
                startTime: $data->startTime,
                source: AppointmentSource::Phone,
                endTime: $data->endTime ?? $start->addMinutes($treatment->session_duration_minutes)->format('H:i'),
                roomId: $data->roomId,
                reason: $data->reason ?? $treatment->title,
                notes: $data->notes,
                treatmentId: $treatment->id,
            ), $number, $treatment->billable_service_id);
        });
    }

    public function create(TreatmentData $data): Treatment
    {
        $actor = Auth::user();
        if ($actor instanceof User && $actor->id !== $data->values['doctor_id'] && ! $actor->can('treatments.write')) {
            throw new AuthorizationException(__('errors.FORBIDDEN'));
        }

        $treatment = DB::transaction(function () use ($data): Treatment {
            $treatment = Treatment::query()->create([
                ...$data->values,
                'session_duration_minutes' => $data->values['session_duration_minutes'] ?? $this->defaultSessionMinutes((string) $data->values['type']),
                'status' => GenericStatus::Pending,
            ]);
            foreach ($data->sessions as $session) {
                $this->bookSession($treatment, new SessionBookingData(
                    $session->providerId, $session->date, $session->startTime, $session->endTime, $session->roomId, $session->reason, $session->notes,
                    overrideDeadline: $data->overrideDeadline,
                ));
            }

            return $treatment;
        });

        TreatmentCreated::dispatch($treatment);

        return $this->find($treatment->id);
    }

    public function update(Treatment $treatment, TreatmentData $data, int $version): Treatment
    {
        return DB::transaction(function () use ($treatment, $data, $version): Treatment {
            $treatment->assertVersion($version);
            $this->assertOpen($treatment);

            // Patient, doctor and the visit it came from stay; priority has its own audited endpoint.
            $treatment->fill(collect($data->values)->except(['patient_id', 'doctor_id', 'appointment_id', 'priority'])->all());
            $used = $treatment->sessions()->whereIn('status', AppointmentStatus::slotHoldingValues())->count();
            if ($treatment->planned_sessions < max(1, $used)) {
                throw ValidationException::withMessages(['planned_sessions' => [__('errors.PLANNED_SESSIONS_TOO_LOW', ['count' => $used])]]);
            }
            $treatment->save();

            return $this->find($treatment->id);
        });
    }

    public function changePriority(Treatment $treatment, TreatmentPriority $priority, string $reason): Treatment
    {
        $this->assertOpen($treatment);
        if ($treatment->priority === $priority) {
            return $treatment;
        }

        DB::transaction(function () use ($treatment, $priority, $reason): void {
            $old = $treatment->priority->value;
            // One audit entry carrying the reason (instead of the generic change entry).
            $treatment->forceFill(['priority' => $priority, 'version' => $treatment->version + 1, 'updated_by' => Auth::id()])->saveQuietly();
            $this->audit->record(AuditAction::Updated, $treatment, ['priority' => $old], ['priority' => $priority->value, 'reason' => $reason]);
        });
        TreatmentPriorityChanged::dispatch($treatment);

        return $this->find($treatment->id);
    }

    public function complete(Treatment $treatment): Treatment
    {
        $this->finish($treatment, GenericStatus::Completed, __('errors.TREATMENT_ENDED'));
        TreatmentCompleted::dispatch($treatment);

        return $this->find($treatment->id);
    }

    public function cancel(Treatment $treatment, string $reason): Treatment
    {
        $this->finish($treatment, GenericStatus::Cancelled, $reason);
        TreatmentCancelled::dispatch($treatment);

        return $this->find($treatment->id);
    }

    public function queue(?string $doctorId = null): Collection
    {
        $now = $this->clock->now();

        return $this->query()
            ->whereIn('status', [GenericStatus::Pending->value, GenericStatus::InProgress->value])
            ->when($doctorId !== null, fn (Builder $q) => $q->where('doctor_id', $doctorId))
            ->with('sessions')
            ->get()
            ->filter(function (Treatment $t) use ($now): bool {
                $booked = $t->sessions->contains(fn (Appointment $s): bool => in_array($s->status, [...self::BOOKED, AppointmentStatus::CheckedIn, AppointmentStatus::InProgress], true) && $s->endsAt()->gt($now));
                $used = $t->sessions->filter(fn (Appointment $s): bool => $s->status->holdsSlot())->count();

                return ! $booked && $used < $t->planned_sessions;
            })
            ->sortBy([fn (Treatment $a, Treatment $b): int => $b->priority->rank() <=> $a->priority->rank(), ['created_at', 'asc']])
            ->values()
            ->toBase();
    }

    public function delete(Model $model): void
    {
        parent::delete($model);
    }

    /** Only finished plans (open ones are cancelled or completed first); also used by bulk archive. */
    public function archive(Model $model): Treatment
    {
        if ($model->isOpen()) {
            throw new InvalidStateTransitionException(context: ['from' => $model->status->value, 'to' => 'archived']);
        }

        return $model->isArchived() ? $model : $this->find(parent::archive($model)->id);
    }

    public function unarchive(Model $model): Treatment
    {
        return $model->isArchived() ? $this->find(parent::unarchive($model)->id) : $model;
    }

    public function restore(Model $model): Treatment
    {
        return $model->isDeleted() ? $this->find(parent::restore($model)->id) : $model;
    }

    public function sessionProgressed(Appointment $session): void
    {
        if ($session->treatment_id === null) {
            return;
        }

        DB::transaction(function () use ($session): void {
            $treatment = Treatment::query()->lockForUpdate()->find($session->treatment_id);
            if (! $treatment instanceof Treatment || ! $treatment->isOpen()) {
                return;
            }
            if ($treatment->status === GenericStatus::Pending) {
                $treatment->forceFill(['status' => GenericStatus::InProgress, 'start_date' => $treatment->start_date ?? $session->scheduled_date])->save();
            }

            $completed = $treatment->sessions()->where('status', AppointmentStatus::Completed->value)->count();
            if ($completed >= $treatment->planned_sessions) {
                $this->complete($treatment);
            }
        });
    }

    public function sessionMissed(Appointment $session): void
    {
        $treatment = $session->treatment_id === null ? null : Treatment::query()->with('sessions')->find($session->treatment_id);
        if (! $treatment instanceof Treatment || ! $treatment->isOpen()) {
            return;
        }

        $absences = $this->progress($treatment)->consecutiveAbsences;
        if ($absences >= Config::integer('appointments.treatments.absence_alert_after')) {
            TreatmentAttendanceAlert::dispatch($treatment, $absences);
        }
    }

    /**
     * @return Builder<Treatment>
     */
    protected function query(): Builder
    {
        return OwnershipScope::apply(Treatment::query()->with(self::RELATIONS), 'treatments', Auth::user());
    }

    /** Only a pending plan that nobody attended yet; bookings still held block it (cancel instead). */
    protected function beforeDelete(Model $model): void
    {
        /** @var Treatment $model */
        $used = $model->sessions()->where(fn (Builder $q) => $q->where('is_attended', true)->orWhereIn('status', AppointmentStatus::slotHoldingValues()))->exists();
        if ($model->status !== GenericStatus::Pending || $used) {
            throw new InvalidStateTransitionException(context: ['from' => $model->status->value, 'to' => 'deleted']);
        }
    }

    private function finish(Treatment $treatment, GenericStatus $to, string $reason): void
    {
        if (! $treatment->status->canTransitionTo($to)) {
            throw new InvalidStateTransitionException(context: ['from' => $treatment->status->value, 'to' => $to->value]);
        }

        DB::transaction(function () use ($treatment, $to, $reason): void {
            $treatment->forceFill([
                'status' => $to,
                'end_date' => $this->clock->today(),
                'cancellation_reason' => $to === GenericStatus::Cancelled ? $reason : null,
            ])->save();

            $treatment->sessions()->whereIn('status', array_map(fn (AppointmentStatus $s): string => $s->value, self::BOOKED))->get()
                ->each(fn (Appointment $s) => $this->appointments->cancel($s, new CancelAppointmentData(CancelledBy::Clinic, $reason)));
        });
    }

    private function assertOpen(Treatment $treatment): void
    {
        if (! $treatment->isOpen()) {
            throw new TreatmentNotOpenException;
        }
    }

    /** BR-02-TREA-02: urgent → first session within 24 h of the prescription, high → 72 h. */
    private function assertFirstSessionDeadline(Treatment $treatment, CarbonImmutable $start, bool $override): void
    {
        $hours = $treatment->priority->deadlineHours();
        $from = $treatment->created_at ?? $this->clock->now();
        if ($hours === null || $start->lte($from->addHours($hours))) {
            return;
        }

        $actor = Auth::user();
        if (! $override || ! $actor instanceof User || ! $actor->can('treatments.override_priority')) {
            throw new PriorityDeadlineException(__('errors.PRIORITY_DEADLINE_MISSED', ['hours' => $hours]), ['hours' => $hours]);
        }
        $this->audit->record(AuditAction::Approved, $treatment, [], ['priority_deadline_override' => $start->toIso8601String()]);
    }

    private function defaultSessionMinutes(string $type): int
    {
        $defaults = Config::array('appointments.treatments.session_minutes');

        return (int) ($defaults[$type] ?? $defaults['default'] ?? 30);
    }
}
