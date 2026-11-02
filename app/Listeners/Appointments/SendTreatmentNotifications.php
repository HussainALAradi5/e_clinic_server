<?php

declare(strict_types=1);

namespace App\Listeners\Appointments;

use App\Enums\Appointments\TreatmentPriority;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserStatus;
use App\Events\Appointments\TreatmentAttendanceAlert;
use App\Events\Appointments\TreatmentCompleted;
use App\Events\Appointments\TreatmentCreated;
use App\Interfaces\Identity\NotificationServiceInterface;
use App\Models\Appointments\Treatment;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Collection;

/**
 * Urgent plans and attendance alerts go to the front desk (and the doctor) in the app; the patient is told when a
 * plan is completed. Minimum PHI: plan title and patient name for staff, nothing clinical for the patient.
 */
final class SendTreatmentNotifications implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly NotificationServiceInterface $notifications) {}

    public function handle(TreatmentCreated|TreatmentAttendanceAlert|TreatmentCompleted $event): void
    {
        $treatment = $event->treatment->loadMissing(['patient.person', 'doctor.person']);
        $data = ['code' => $treatment->code, 'title' => $treatment->title, 'patient' => $treatment->patient->person->full_name];

        match (true) {
            $event instanceof TreatmentCreated => $treatment->priority === TreatmentPriority::Urgent
                ? $this->toStaff('treatment.urgent', $treatment, $data, withDoctor: false)
                : null,
            $event instanceof TreatmentAttendanceAlert => $this->toStaff('treatment.attendance_alert', $treatment, [...$data, 'count' => (string) $event->consecutiveAbsences], withDoctor: true),
            default => $this->notifications->send('treatment.completed', $treatment->patient->person, $treatment, ['code' => $treatment->code]),
        };
    }

    /**
     * @param  array<string, string>  $data
     */
    private function toStaff(string $template, Treatment $treatment, array $data, bool $withDoctor): void
    {
        /** @var Collection<int, Person> $people */
        $people = User::role(RoleName::Receptionist->value)->where('status', UserStatus::Active)->with('person')->get()->pluck('person');
        if ($withDoctor) {
            $people->push($treatment->doctor->person);
        }
        $people->filter()->unique('id')->each(fn (Person $p) => $this->notifications->send($template, $p, $treatment, $data));
    }
}
