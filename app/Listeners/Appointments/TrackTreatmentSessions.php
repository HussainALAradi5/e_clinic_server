<?php

declare(strict_types=1);

namespace App\Listeners\Appointments;

use App\Events\Appointments\AppointmentCompleted;
use App\Events\Appointments\AppointmentNoShow;
use App\Events\Appointments\AppointmentStarted;
use App\Events\Appointments\PatientCheckedIn;
use App\Interfaces\Appointments\TreatmentServiceInterface;

/**
 * Keeps a plan's status in step with its sessions: the first attended session starts it, the last completed one
 * completes it; missed sessions in a row raise the attendance alert. Runs in the session's transaction.
 */
final class TrackTreatmentSessions
{
    public function __construct(private readonly TreatmentServiceInterface $treatments) {}

    public function handle(PatientCheckedIn|AppointmentStarted|AppointmentCompleted|AppointmentNoShow $event): void
    {
        if ($event->appointment->treatment_id === null) {
            return;
        }

        $event instanceof AppointmentNoShow
            ? $this->treatments->sessionMissed($event->appointment)
            : $this->treatments->sessionProgressed($event->appointment);
    }
}
