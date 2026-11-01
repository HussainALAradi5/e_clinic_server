<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Patients\Listeners;

use App\Modules\Appointments\Patients\Events\EmergencyAccessGranted;
use App\Modules\Identity\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Identity\Users\Models\Person;
use App\Shared\Support\Recipients;
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
