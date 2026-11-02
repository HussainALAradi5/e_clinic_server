<?php

declare(strict_types=1);

namespace App\Listeners\Appointments;

use App\Events\Appointments\AppointmentBooked;
use App\Events\Appointments\AppointmentCancelled;
use App\Events\Appointments\AppointmentRescheduled;
use App\Models\Appointments\Appointment;
use App\Notifications\Identity\Contracts\NotificationServiceInterface;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Config;

/**
 * Patient messages of the booking lifecycle (minimum PHI: date, time, provider). A booking also queues its
 * reminder; cancelling or moving it cancels the queued reminder. Runs after commit (a rolled-back booking sends nothing).
 */
final class SendAppointmentNotifications implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly NotificationServiceInterface $notifications) {}

    public function handle(AppointmentBooked|AppointmentCancelled|AppointmentRescheduled $event): void
    {
        $appointment = $event->appointment->loadMissing(['patient.person', 'provider.person']);

        match (true) {
            $event instanceof AppointmentBooked => $this->booked($appointment),
            $event instanceof AppointmentCancelled => $this->cancelled($appointment),
            default => $this->notifications->cancelFor($appointment),
        };
    }

    private function booked(Appointment $appointment): void
    {
        $template = $appointment->rescheduled_from_id !== null ? 'appointment.rescheduled' : 'appointment.booked';
        $this->notifications->send($template, $appointment->patient->person, $appointment, $this->data($appointment));

        $remindAt = $appointment->startsAt()->subHours(Config::integer('appointments.reminder_hours_before'));
        if ($remindAt->isFuture()) {
            $this->notifications->send('appointment.reminder', $appointment->patient->person, $appointment, $this->data($appointment), $remindAt);
        }
    }

    private function cancelled(Appointment $appointment): void
    {
        $this->notifications->cancelFor($appointment);
        $this->notifications->send('appointment.cancelled', $appointment->patient->person, $appointment, $this->data($appointment));
    }

    /**
     * @return array<string, string>
     */
    private function data(Appointment $appointment): array
    {
        return [
            'code' => $appointment->code,
            'date' => $appointment->scheduled_date->format('D j M Y'),
            'time' => substr($appointment->start_time, 0, 5),
            'provider' => $appointment->provider->person->full_name,
        ];
    }
}
