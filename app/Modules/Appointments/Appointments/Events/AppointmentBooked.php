<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Appointments\Events;

use App\Modules\Appointments\Appointments\Models\Appointment;
use Illuminate\Foundation\Events\Dispatchable;

final class AppointmentBooked
{
    use Dispatchable;

    public function __construct(public readonly Appointment $appointment) {}
}
