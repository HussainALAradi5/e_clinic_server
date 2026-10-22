<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Appointments\Events;

use App\Modules\Appointments\Appointments\Data\CompleteAppointmentData;
use App\Modules\Appointments\Appointments\Models\Appointment;
use Illuminate\Foundation\Events\Dispatchable;

final class AppointmentCompleted
{
    use Dispatchable;

    public function __construct(public readonly Appointment $appointment, public readonly ?CompleteAppointmentData $data = null) {}
}
