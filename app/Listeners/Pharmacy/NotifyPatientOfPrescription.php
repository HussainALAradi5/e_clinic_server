<?php

declare(strict_types=1);

namespace App\Listeners\Pharmacy;

use App\Events\Pharmacy\PrescriptionCreated;
use App\Interfaces\Identity\NotificationServiceInterface;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Tells the patient (app) that a prescription is waiting at the pharmacy, with its code and expiry.
 */
final class NotifyPatientOfPrescription implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly NotificationServiceInterface $notifications) {}

    public function handle(PrescriptionCreated $event): void
    {
        $prescription = $event->prescription->loadMissing('patient.person', 'doctor.person');
        $this->notifications->send('prescription.created', $prescription->patient->person, $prescription, [
            'code' => $prescription->code,
            'doctor' => $prescription->doctor->person->full_name,
            'valid_until' => $prescription->valid_until->toDateString(),
        ]);
    }
}
