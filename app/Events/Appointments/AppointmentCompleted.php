<?php

declare(strict_types=1);

namespace App\Events\Appointments;

use App\Dtos\Appointments\CompleteAppointmentData;
use App\Models\Appointments\Appointment;
use Illuminate\Foundation\Events\Dispatchable;

final class AppointmentCompleted
{
    use Dispatchable;

    public function __construct(public readonly Appointment $appointment, public readonly ?CompleteAppointmentData $data = null) {}
}
