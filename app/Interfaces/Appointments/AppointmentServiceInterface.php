<?php

declare(strict_types=1);

namespace App\Interfaces\Appointments;

use App\Dtos\Appointments\BookAppointmentData;
use App\Dtos\Appointments\CalendarQueryData;
use App\Dtos\Appointments\CancelAppointmentData;
use App\Dtos\Appointments\CompleteAppointmentData;
use App\Dtos\Appointments\RescheduleData;
use App\Dtos\Appointments\SlotData;
use App\Enums\Appointments\AppointmentType;
use App\Enums\Appointments\VisitTiming;
use App\Models\Appointments\Appointment;
use App\Models\HR\Employee;
use App\Models\Identity\AuditLog;
use App\Utilities\Query\ListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AppointmentServiceInterface
{
    /**
     * Search by date range, provider, patient, type, status (providers see their own, patients theirs).
     *
     * @return LengthAwarePaginator<int, Appointment>|CursorPaginator<int, Appointment>
     */
    public function list(ListQuery $query, ?VisitTiming $timing = null): LengthAwarePaginator|CursorPaginator;

    /**
     * Bookings of providers / rooms in a range (≤ 31 days), cancelled and rescheduled ones left out.
     *
     * @return Collection<int, Appointment>
     */
    public function calendar(CalendarQueryData $query): Collection;

    /** Booking with patient, provider, room and billed service. */
    public function find(string $id): Appointment;

    /**
     * Free slots of a provider (and optionally a room) on a date.
     *
     * @return Collection<int, SlotData>
     */
    public function getAvailableSlots(Employee $provider, CarbonImmutable $date, AppointmentType $type, ?string $roomId = null): Collection;

    /**
     * Staff who can take this kind of booking today: bookable doctors, plus active medical staff for sessions.
     *
     * @return Collection<int, Employee>
     */
    public function providers(AppointmentType $type): Collection;

    /** Books a visit: window, working hours, provider / patient / room overlap and booking horizon are enforced. */
    public function book(BookAppointmentData $data): Appointment;

    /** **Internal** (Treatments) — books one session of a plan with the same rules as a visit. */
    public function bookSession(BookAppointmentData $data, int $sessionNumber, ?string $billableServiceId): Appointment;

    public function confirm(Appointment $appointment): Appointment;

    /** Front desk or QR scan, on the scheduled date only. */
    public function checkIn(Appointment $appointment): Appointment;

    /** Assigned provider (or same-department staff with appointments.treat). */
    public function start(Appointment $appointment): Appointment;

    public function complete(Appointment $appointment, CompleteAppointmentData $data): Appointment;

    /** Pending / confirmed only; patients up to the change window before the start. */
    public function cancel(Appointment $appointment, CancelAppointmentData $data): Appointment;

    /** New booking linked by rescheduled_from_id; the original becomes `rescheduled`. */
    public function reschedule(Appointment $appointment, RescheduleData $data): Appointment;

    /** Confirmed and not checked in after the grace period. */
    public function markNoShow(Appointment $appointment): Appointment;

    /** Scheduler: marks every overdue confirmed booking as no-show; returns how many. */
    public function markDueNoShows(): int;

    /**
     * Status changes from the audit trail, oldest first.
     *
     * @return Collection<int, AuditLog>
     */
    public function history(Appointment $appointment): Collection;
}
