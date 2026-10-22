<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Treatments\Data;

use App\Enums\Appointments\AppointmentStatus;
use App\Modules\Appointments\Appointments\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Attendance and progress computed from the session appointments (never stored, so it never drifts).
 * attended = the patient came (checked in); absent = no-show; rescheduled originals are ignored.
 */
final readonly class TreatmentProgress
{
    public function __construct(
        public int $planned,
        public int $attended,
        public int $completed,
        public int $absent,
        public int $cancelled,
        public int $upcoming,
        public int $consecutiveAbsences,
        public ?CarbonImmutable $lastAttendedAt,
        public ?Appointment $nextSession,
    ) {}

    /**
     * @param  Collection<int, Appointment>  $sessions  ordered by date / time
     */
    public static function of(int $planned, Collection $sessions, CarbonImmutable $now): self
    {
        $counted = $sessions->reject(fn (Appointment $s): bool => $s->status === AppointmentStatus::Rescheduled);
        $attended = $counted->filter(fn (Appointment $s): bool => $s->is_attended);
        $upcoming = $counted->filter(fn (Appointment $s): bool => in_array($s->status, [AppointmentStatus::Pending, AppointmentStatus::Confirmed], true) && $s->endsAt()->gt($now));

        // Absences in a row, counting back from the latest session that already happened.
        $consecutive = 0;
        foreach ($counted->reverse() as $session) {
            if ($session->status === AppointmentStatus::NoShow) {
                $consecutive++;
            } elseif ($session->is_attended) {
                break;
            }
        }

        $last = $attended->last();

        return new self(
            planned: $planned,
            attended: $attended->count(),
            completed: $counted->where('status', AppointmentStatus::Completed)->count(),
            absent: $counted->where('status', AppointmentStatus::NoShow)->count(),
            cancelled: $counted->where('status', AppointmentStatus::Cancelled)->count(),
            upcoming: $upcoming->count(),
            consecutiveAbsences: $consecutive,
            lastAttendedAt: $last->checked_in_at ?? $last?->startsAt(),
            nextSession: $upcoming->first(),
        );
    }

    public function remaining(): int
    {
        return max(0, $this->planned - $this->attended);
    }

    /** attended ÷ (attended + absent), null before the first session. */
    public function attendanceRate(): ?float
    {
        $held = $this->attended + $this->absent;

        return $held === 0 ? null : round($this->attended / $held * 100, 1);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'planned_sessions' => $this->planned,
            'attended_sessions' => $this->attended,
            'completed_sessions' => $this->completed,
            'absent_sessions' => $this->absent,
            'cancelled_sessions' => $this->cancelled,
            'upcoming_sessions' => $this->upcoming,
            'remaining_sessions' => $this->remaining(),
            'attendance_rate' => $this->attendanceRate(),
            'consecutive_absences' => $this->consecutiveAbsences,
            'last_attended_at' => $this->lastAttendedAt?->toIso8601String(),
            'next_session' => $this->nextSession === null ? null : [
                'id' => $this->nextSession->id,
                'scheduled_date' => $this->nextSession->scheduled_date->toDateString(),
                'start_time' => substr($this->nextSession->start_time, 0, 5),
            ],
        ];
    }
}
