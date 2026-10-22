<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Listeners;

use App\Modules\Appointments\Doctors\Models\Doctor;
use App\Modules\HR\Employees\Events\EmployeeTerminated;

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
