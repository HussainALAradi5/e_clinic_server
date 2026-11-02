<?php

declare(strict_types=1);

namespace App\Listeners\Appointments;

use App\Events\Appointments\EmergencyAccessGranted;
use App\Models\Identity\Person;
use App\Notifications\Identity\Contracts\NotificationServiceInterface;
use App\Utilities\Shared\Recipients;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Every break-the-glass reaches the people who review the audit trail (admins), who can follow it up. */
final class NotifyEmergencyAccess implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly NotificationServiceInterface $notifications) {}

    public function handle(EmergencyAccessGranted $event): void
    {
        $grant = $event->grant->loadMissing('user.person');
        $data = ['clinician' => $grant->user->person->full_name, 'patient' => $event->patient->person->code, 'reason' => $grant->reason];

        Recipients::withPermission('audit.read_all', [$grant->user_id])
            ->unique('id')
            ->each(fn (Person $p) => $this->notifications->send('patient.emergency_access', $p, $event->patient, $data));
    }
}
