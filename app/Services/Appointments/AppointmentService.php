<?php

declare(strict_types=1);

namespace App\Services\Appointments;

use App\Dtos\Appointments\BookAppointmentData;
use App\Dtos\Appointments\CalendarQueryData;
use App\Dtos\Appointments\CancelAppointmentData;
use App\Dtos\Appointments\CompleteAppointmentData;
use App\Dtos\Appointments\RescheduleData;
use App\Dtos\Appointments\SlotData;
use App\Dtos\Appointments\WorkingWindow;
use App\Enums\Appointments\AppointmentSource;
use App\Enums\Appointments\AppointmentStatus;
use App\Enums\Appointments\AppointmentType;
use App\Enums\HR\EmployeeStatus;
use App\Enums\Identity\UserType;
use App\Events\Appointments\AppointmentBooked;
use App\Events\Appointments\AppointmentCancelled;
use App\Events\Appointments\AppointmentCompleted;
use App\Events\Appointments\AppointmentConfirmed;
use App\Events\Appointments\AppointmentNoShow;
use App\Events\Appointments\AppointmentRescheduled;
use App\Events\Appointments\AppointmentStarted;
use App\Events\Appointments\PatientCheckedIn;
use App\Exceptions\Appointments\BookingWindowExceededException;
use App\Exceptions\Appointments\CancellationWindowExpiredException;
use App\Exceptions\Appointments\DoctorNotAvailableException;
use App\Exceptions\Appointments\PatientDoubleBookingException;
use App\Exceptions\Appointments\RoomUnavailableException;
use App\Exceptions\Appointments\SlotUnavailableException;
use App\Exceptions\Shared\InvalidStateTransitionException;
use App\Interfaces\Appointments\AppointmentServiceInterface;
use App\Interfaces\Appointments\ConsentServiceInterface;
use App\Interfaces\Appointments\ScheduleServiceInterface;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\Doctor;
use App\Models\Appointments\Patient;
use App\Models\Appointments\Room;
use App\Models\HR\Employee;
use App\Models\Identity\AuditLog;
use App\Models\Identity\User;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AppointmentService implements AppointmentServiceInterface
{
    public const RELATIONS = ['patient.person', 'provider.person', 'room', 'billableService'];

    /** Booking kinds that need a doctor; treatment sessions also accept medical staff. */
    private const DOCTOR_ONLY = [AppointmentType::Consultation, AppointmentType::FollowUp, AppointmentType::Procedure, AppointmentType::Emergency, AppointmentType::Teleconsultation];

    public function __construct(
        private readonly ApplyListQuery $listQuery,
        private readonly ScheduleServiceInterface $schedules,
        private readonly ClinicClock $clock,
        private readonly ConsentServiceInterface $consents,
    ) {}

    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->appointments(), $query);
    }

    public function calendar(CalendarQueryData $query): Collection
    {
        return $this->appointments()
            ->whereDate('scheduled_date', '>=', $query->from->toDateString())
            ->whereDate('scheduled_date', '<=', $query->to->toDateString())
            ->whereNotIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::Rescheduled->value])
            ->when($query->providerId !== null, fn (Builder $q) => $q->where('provider_id', $query->providerId))
            ->when($query->roomId !== null, fn (Builder $q) => $q->where('room_id', $query->roomId))
            ->orderBy('scheduled_date')->orderBy('start_time')
            ->get();
    }

    public function find(string $id): Appointment
    {
        return $this->appointments()->with(['rescheduledFrom', 'invoiceLine.invoice'])->findOrFail($id);
    }

    public function getAvailableSlots(Employee $provider, CarbonImmutable $date, AppointmentType $type, ?string $roomId = null): Collection
    {
        $doctor = $this->assertProviderBookable($provider, $type, $date);
        $length = $this->slotLength($doctor);
        if ($this->dailyCapReached($doctor, $date)) {
            return collect();
        }

        $busy = Appointment::query()->whereDate('scheduled_date', $date->toDateString())
            ->whereIn('status', AppointmentStatus::slotHoldingValues())
            ->where(fn (Builder $q) => $q->where('provider_id', $provider->id)->when($roomId !== null, fn (Builder $q) => $q->orWhere('room_id', $roomId)))
            ->get(['start_time', 'end_time'])
            ->map(fn (Appointment $a): array => [$this->at($date, $a->start_time), $this->at($date, $a->end_time)]);
        $now = $this->clock->now();

        return $this->schedules->getWorkingWindows($provider, $date)
            ->flatMap(function (WorkingWindow $window) use ($length): array {
                $minutes = $window->slotMinutes ?? $length;
                $slots = [];
                for ($start = $window->start; $start->addMinutes($minutes)->lte($window->end); $start = $start->addMinutes($minutes)) {
                    $slots[] = new SlotData($start, $start->addMinutes($minutes));
                }

                return $slots;
            })
            ->reject(fn (SlotData $slot): bool => $slot->start->lt($now)
                || $busy->contains(fn (array $b): bool => $slot->start->lt($b[1]) && $slot->end->gt($b[0])))
            ->values();
    }

    public function providers(AppointmentType $type): Collection
    {
        $today = $this->clock->today();
        $doctors = Doctor::query()->with(['employee.person', 'employee.department', 'specialty'])->get()
            ->filter(fn (Doctor $d): bool => $d->isBookable($today))
            ->map(fn (Doctor $d): Employee => $d->employee->setRelation('doctor', $d));
        if (in_array($type, self::DOCTOR_ONLY, true)) {
            return $doctors->values()->toBase();
        }

        $staff = Employee::query()->with(['person', 'department'])
            ->where('status', EmployeeStatus::Active->value)
            ->whereHas('user', fn (Builder $q) => $q->where('user_type', UserType::MedicalStaff->value))
            ->get();

        return $doctors->concat($staff)->unique('id')->sortBy(fn (Employee $e): string => $e->person->full_name)->values()->toBase();
    }

    public function book(BookAppointmentData $data): Appointment
    {
        if ($data->type === AppointmentType::TreatmentSession) {
            // Sessions are booked from their treatment plan (Treatments module).
            throw ValidationException::withMessages(['type' => [__('validation.in', ['attribute' => 'type'])]]);
        }

        return $this->place($data);
    }

    public function bookSession(BookAppointmentData $data, int $sessionNumber, ?string $billableServiceId): Appointment
    {
        return $this->place($data, ['treatment_id' => $data->treatmentId, 'session_number' => $sessionNumber, 'billable_service_id' => $billableServiceId]);
    }

    /**
     * Shared booking engine (visits and treatment sessions).
     *
     * @param  array{treatment_id?: ?string, session_number?: int, billable_service_id?: ?string}  $session
     */
    private function place(BookAppointmentData $data, array $session = []): Appointment
    {
        $provider = Employee::query()->with('user')->findOrFail($data->providerId);
        $patient = Patient::query()->findOrFail($data->patientId);
        $doctor = $this->assertProviderBookable($provider, $data->type, $data->date);

        $start = $this->at($data->date, $data->startTime);
        $end = $data->endTime !== null ? $this->at($data->date, $data->endTime) : $start->addMinutes($this->slotLengthAt($provider, $doctor, $start));
        $this->assertBookingWindow($data, $start, $end);

        $appointment = DB::transaction(function () use ($data, $session, $provider, $patient, $doctor, $start, $end): Appointment {
            // Serialises bookings of one provider (portable stand-in for pg_advisory_xact_lock(provider, day)).
            Employee::query()->whereKey($provider->id)->lockForUpdate()->first();

            if ($this->dailyCapReached($doctor, $data->date)) {
                throw new DoctorNotAvailableException(context: ['reason' => 'daily_limit']);
            }
            $windows = $this->schedules->getWorkingWindows($provider, $data->date);
            if (! $windows->contains(fn (WorkingWindow $w): bool => $w->contains($start, $end))) {
                throw new SlotUnavailableException(context: ['reason' => 'outside_working_hours']);
            }

            $from = $start->format('H:i:s');
            $to = $end->format('H:i:s');
            if (Appointment::query()->overlapping($data->date, $from, $to)->where('provider_id', $provider->id)->exists()) {
                throw new SlotUnavailableException(context: ['reason' => 'taken']);
            }
            if (Appointment::query()->overlapping($data->date, $from, $to)->where('patient_id', $patient->id)->exists()) {
                throw new PatientDoubleBookingException;
            }

            $roomId = $data->roomId ?? $doctor?->default_room_id;
            if ($roomId !== null) {
                $this->assertRoomFree($roomId, $data->type, $data->date, $from, $to);
            }

            try {
                return Appointment::query()->create([
                    'patient_id' => $patient->id,
                    'provider_id' => $provider->id,
                    'type' => $data->type,
                    'scheduled_date' => $data->date,
                    'start_time' => $from,
                    'end_time' => $to,
                    'room_id' => $roomId,
                    'billable_service_id' => array_key_exists('billable_service_id', $session)
                        ? $session['billable_service_id']
                        : ($doctor !== null ? ($data->type === AppointmentType::FollowUp ? $doctor->follow_up_service_id : $doctor->consultation_service_id) : null),
                    'treatment_id' => $session['treatment_id'] ?? null,
                    'session_number' => $session['session_number'] ?? null,
                    'status' => $data->source === AppointmentSource::Portal ? AppointmentStatus::Pending : AppointmentStatus::Confirmed,
                    'source' => $data->source,
                    'reason' => $data->reason,
                    'notes' => $data->notes,
                    'rescheduled_from_id' => $data->rescheduledFromId,
                ]);
            } catch (QueryException $e) {
                // PostgreSQL exclusion constraints are the final guard against a race (SQLSTATE 23P01).
                if ($e->getCode() === '23P01') {
                    throw str_contains($e->getMessage(), 'room') ? new RoomUnavailableException : new SlotUnavailableException(context: ['reason' => 'taken']);
                }
                throw $e;
            }
        });

        AppointmentBooked::dispatch($appointment);

        // The booker just made it: shown even when their read rights are "own" (e.g. a doctor booking a therapist).
        return $this->reload($appointment);
    }

    public function confirm(Appointment $appointment): Appointment
    {
        $this->transition($appointment, AppointmentStatus::Confirmed);
        AppointmentConfirmed::dispatch($appointment);

        return $this->reload($appointment);
    }

    public function checkIn(Appointment $appointment): Appointment
    {
        if ($appointment->scheduled_date->toDateString() !== $this->clock->today()->toDateString()) {
            throw new InvalidStateTransitionException(context: ['from' => $appointment->status->value, 'to' => AppointmentStatus::CheckedIn->value, 'reason' => 'not_today']);
        }
        $this->transition($appointment, AppointmentStatus::CheckedIn, ['checked_in_at' => $this->clock->now(), 'is_attended' => true]);
        PatientCheckedIn::dispatch($appointment);

        return $this->reload($appointment);
    }

    public function start(Appointment $appointment): Appointment
    {
        $this->assertCanTreat($appointment);
        $this->consents->assertConsent($appointment->patient, $appointment->billableService?->consent_type, $appointment);
        $this->transition($appointment, AppointmentStatus::InProgress, ['started_at' => $this->clock->now()]);
        AppointmentStarted::dispatch($appointment);

        return $this->reload($appointment);
    }

    public function complete(Appointment $appointment, CompleteAppointmentData $data): Appointment
    {
        $this->assertCanTreat($appointment);
        $this->transition($appointment, AppointmentStatus::Completed, ['completed_at' => $this->clock->now()]);
        AppointmentCompleted::dispatch($appointment, $data);

        return $this->reload($appointment);
    }

    public function cancel(Appointment $appointment, CancelAppointmentData $data): Appointment
    {
        $this->assertPatientMayChange($appointment);
        $this->transition($appointment, AppointmentStatus::Cancelled, [
            'cancelled_by' => $data->cancelledBy,
            'cancellation_reason' => $data->reason,
            'cancelled_at' => $this->clock->now(),
        ]);
        AppointmentCancelled::dispatch($appointment);

        return $this->reload($appointment);
    }

    public function reschedule(Appointment $appointment, RescheduleData $data): Appointment
    {
        $this->assertPatientMayChange($appointment);
        if (! $appointment->status->canTransitionTo(AppointmentStatus::Rescheduled)) {
            throw new InvalidStateTransitionException(context: ['from' => $appointment->status->value, 'to' => AppointmentStatus::Rescheduled->value]);
        }

        $replacement = DB::transaction(function () use ($appointment, $data): Appointment {
            // Free the old slot first so the new time may overlap it (e.g. 15 minutes later).
            $this->transition($appointment, AppointmentStatus::Rescheduled);

            $session = $appointment->treatment_id !== null;
            // Sessions keep their length; visits take the slot length at the new time.
            $minutes = (int) $appointment->startsAt()->diffInMinutes($appointment->endsAt());

            return $this->place(new BookAppointmentData(
                patientId: $appointment->patient_id,
                providerId: $data->providerId ?? $appointment->provider_id,
                type: $appointment->type,
                date: $data->date,
                startTime: $data->startTime,
                source: $this->isPatientActor($appointment) ? AppointmentSource::Portal : $appointment->source,
                endTime: $session ? $this->at($data->date, $data->startTime)->addMinutes($minutes)->format('H:i') : null,
                roomId: $data->roomId ?? ($data->providerId === null ? $appointment->room_id : null),
                reason: $appointment->reason,
                notes: $appointment->notes,
                treatmentId: $appointment->treatment_id,
                rescheduledFromId: $appointment->id,
            ), $session ? [
                'treatment_id' => $appointment->treatment_id,
                'session_number' => (int) $appointment->session_number,
                'billable_service_id' => $appointment->billable_service_id,
            ] : []);
        });

        AppointmentRescheduled::dispatch($appointment->refresh(), $replacement);

        return $replacement;
    }

    public function markNoShow(Appointment $appointment): Appointment
    {
        $grace = Config::integer('appointments.no_show_after_minutes');
        if ($this->clock->now()->lt($appointment->startsAt()->addMinutes($grace))) {
            throw new InvalidStateTransitionException(context: ['from' => $appointment->status->value, 'to' => AppointmentStatus::NoShow->value, 'reason' => 'too_early']);
        }
        $this->transition($appointment, AppointmentStatus::NoShow);
        AppointmentNoShow::dispatch($appointment);

        return $this->reload($appointment);
    }

    public function markDueNoShows(): int
    {
        $cutoff = $this->clock->now()->subMinutes(Config::integer('appointments.no_show_after_minutes'));
        $due = Appointment::query()
            ->where('status', AppointmentStatus::Confirmed)
            ->whereDate('scheduled_date', '<=', $this->clock->toClinic($cutoff)->toDateString())
            ->get()
            ->filter(fn (Appointment $a): bool => $a->startsAt()->lte($cutoff));

        foreach ($due as $appointment) {
            $this->transition($appointment, AppointmentStatus::NoShow);
            AppointmentNoShow::dispatch($appointment);
        }

        return $due->count();
    }

    public function history(Appointment $appointment): Collection
    {
        // Whoever may read the booking may read its status trail (status values only, no other audit data).
        return AuditLog::query()->with('user.person')
            ->where('auditable_type', $appointment->getMorphClass())
            ->where('auditable_id', $appointment->id)
            ->orderBy('id')
            ->get()
            ->filter(fn (AuditLog $log): bool => array_key_exists('status', (array) $log->new_values))
            ->values()
            ->toBase();
    }

    /** Fresh copy after a change the caller was allowed to make (internal callers may lack read rights on it). */
    private function reload(Appointment $appointment): Appointment
    {
        return Appointment::query()->with([...self::RELATIONS, 'rescheduledFrom', 'invoiceLine.invoice'])->findOrFail($appointment->id);
    }

    /**
     * @return Builder<Appointment>
     */
    private function appointments(): Builder
    {
        return OwnershipScope::apply(Appointment::query()->with(self::RELATIONS), 'appointments', Auth::user());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transition(Appointment $appointment, AppointmentStatus $to, array $attributes = []): void
    {
        if (! $appointment->status->canTransitionTo($to)) {
            throw new InvalidStateTransitionException(context: ['from' => $appointment->status->value, 'to' => $to->value]);
        }
        $appointment->forceFill([...$attributes, 'status' => $to])->save();
    }

    /** Doctor-only kinds need a bookable doctor; sessions accept an active doctor or medical staff. */
    private function assertProviderBookable(Employee $provider, AppointmentType $type, CarbonImmutable $date): ?Doctor
    {
        $doctor = Doctor::query()->with('employee')->find($provider->id);

        if (in_array($type, self::DOCTOR_ONLY, true)) {
            if ($doctor === null || ! $doctor->isBookable($date)) {
                throw new DoctorNotAvailableException;
            }

            return $doctor;
        }

        $staff = in_array($provider->user->user_type, [UserType::Doctor, UserType::MedicalStaff], true);
        if ($provider->status !== EmployeeStatus::Active || ! $staff) {
            throw new DoctorNotAvailableException;
        }

        return $doctor;
    }

    private function assertBookingWindow(BookAppointmentData $data, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $now = $this->clock->now();
        $horizon = Config::integer('appointments.booking_horizon_days');
        if ($data->date->toDateString() > $this->clock->today()->addDays($horizon)->toDateString()) {
            throw new BookingWindowExceededException(__('errors.BOOKING_WINDOW_EXCEEDED', ['days' => $horizon]));
        }
        $lead = Config::integer('appointments.portal_min_hours_ahead');
        if ($data->source === AppointmentSource::Portal && $start->lt($now->addHours($lead))) {
            throw new BookingWindowExceededException(__('errors.BOOKING_TOO_SOON', ['hours' => $lead]));
        }
        if ($end->lte($now) || $end->lte($start)) {
            throw new SlotUnavailableException(context: ['reason' => 'past']);
        }
    }

    private function assertRoomFree(string $roomId, AppointmentType $type, CarbonImmutable $date, string $from, string $to): void
    {
        $room = Room::query()->find($roomId);
        $unusable = ! $room instanceof Room || ! $room->isBookable() || ! in_array($room->type, Room::typesFor($type), true);
        if ($unusable || Appointment::query()->overlapping($date, $from, $to)->where('room_id', $roomId)->exists()) {
            throw new RoomUnavailableException;
        }
    }

    /** Assigned provider, or same-department staff with appointments.treat (super admins too). */
    private function assertCanTreat(Appointment $appointment): void
    {
        $actor = Auth::user();
        if (! $actor instanceof User || $actor->id === $appointment->provider_id || $actor->isSuperAdmin()) {
            return;
        }
        $sameDepartment = Employee::query()->whereKey($actor->id)
            ->where('department_id', Employee::query()->whereKey($appointment->provider_id)->value('department_id'))
            ->exists();
        if (! $sameDepartment || ! $actor->can('appointments.treat')) {
            throw new AuthorizationException(__('errors.FORBIDDEN'));
        }
    }

    /** Patients (portal) may change their own booking only up to the change window before the start. */
    private function assertPatientMayChange(Appointment $appointment): void
    {
        if ($this->isPatientActor($appointment)
            && $this->clock->now()->gt($appointment->startsAt()->subHours(Config::integer('appointments.patient_change_hours')))) {
            throw new CancellationWindowExpiredException;
        }
    }

    private function isPatientActor(Appointment $appointment): bool
    {
        $actor = Auth::user();

        return $actor instanceof User && $actor->user_type === UserType::Patient && $actor->id === $appointment->patient_id;
    }

    private function dailyCapReached(?Doctor $doctor, CarbonImmutable $date): bool
    {
        return $doctor?->max_daily_appointments !== null && Appointment::query()
            ->where('provider_id', $doctor->id)
            ->whereDate('scheduled_date', $date->toDateString())
            ->whereIn('status', AppointmentStatus::slotHoldingValues())
            ->count() >= $doctor->max_daily_appointments;
    }

    private function slotLength(?Doctor $doctor): int
    {
        return $doctor !== null ? $doctor->default_slot_minutes : Config::integer('appointments.default_slot_minutes');
    }

    /** Slot length of the working block containing the start, else the doctor's / clinic default. */
    private function slotLengthAt(Employee $provider, ?Doctor $doctor, CarbonImmutable $start): int
    {
        $window = $this->schedules->getWorkingWindows($provider, $start->startOfDay())
            ->first(fn (WorkingWindow $w): bool => $start->gte($w->start) && $start->lt($w->end));

        return $window instanceof WorkingWindow && $window->slotMinutes !== null ? $window->slotMinutes : $this->slotLength($doctor);
    }

    /** Clinic-local date + time → instant. */
    private function at(CarbonImmutable $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString().' '.$time, $this->clock->timezone());
    }
}
