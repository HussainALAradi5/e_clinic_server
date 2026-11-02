<?php

declare(strict_types=1);

namespace App\Listeners\Appointments;

use App\Events\HR\EmployeeTerminated;
use App\Models\Appointments\Doctor;

/**
 * A terminated doctor stops accepting new bookings (future ones stay for the desk to reassign).
 */
final class StopBookingsOfTerminatedDoctor
{
    public function handle(EmployeeTerminated $event): void
    {
        Doctor::query()->whereKey($event->employee->id)->update(['is_accepting_patients' => false]);
    }
}
