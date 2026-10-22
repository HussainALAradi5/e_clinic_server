<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Treatments\Contracts;

use App\Enums\Appointments\TreatmentPriority;
use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Treatments\Data\SessionBookingData;
use App\Modules\Appointments\Treatments\Data\TreatmentData;
use App\Modules\Appointments\Treatments\Data\TreatmentProgress;
use App\Modules\Appointments\Treatments\Models\Treatment;
use App\Shared\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TreatmentServiceInterface
{
    /**
     * By patient, doctor, status, priority (doctors see their own and their sessions' plans, patients their own).
     *
     * @return LengthAwarePaginator<int, Treatment>|CursorPaginator<int, Treatment>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    public function find(string $id): Treatment;

    public function findWithDeleted(string $id): Treatment;

    /** Attendance and progress computed from the session appointments. */
    public function progress(Treatment $treatment): TreatmentProgress;

    /**
     * Sessions in order (rescheduled originals left out).
     *
     * @return Collection<int, Appointment>
     */
    public function sessions(Treatment $treatment): Collection;

    /** Books the next session (reuses the number of an absent / cancelled one). */
    public function bookSession(Treatment $treatment, SessionBookingData $data): Appointment;

    /** Prescribes a plan (`pending`), optionally with its first sessions. */
    public function create(TreatmentData $data): Treatment;

    /** Type, title, diagnosis, plan, planned sessions, session length and service of an open plan. */
    public function update(Treatment $treatment, TreatmentData $data, int $version): Treatment;

    public function changePriority(Treatment $treatment, TreatmentPriority $priority, string $reason): Treatment;

    /** `→ completed` (also automatic when the completed sessions reach the plan); remaining bookings are cancelled. */
    public function complete(Treatment $treatment): Treatment;

    /** `→ cancelled`; remaining bookings are cancelled. */
    public function cancel(Treatment $treatment, string $reason): Treatment;

    /**
     * Open plans without a booked next session, urgent → high → medium, oldest first (reception work list).
     *
     * @return Collection<int, Treatment>
     */
    public function queue(?string $doctorId = null): Collection;

    /** Soft-deletes a `pending` plan with no attended session. */
    public function delete(Treatment $treatment): void;

    public function archive(Treatment $treatment): Treatment;

    public function unarchive(Treatment $treatment): Treatment;

    public function restore(Treatment $treatment): Treatment;

    /** **Listener** — a session was checked in, started or completed: starts the plan, completes it when done. */
    public function sessionProgressed(Appointment $session): void;

    /** **Listener** — a session was missed: raises the attendance alert after N absences in a row. */
    public function sessionMissed(Appointment $session): void;
}
