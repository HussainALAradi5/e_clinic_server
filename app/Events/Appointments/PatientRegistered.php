<?php

declare(strict_types=1);

namespace App\Events\Appointments;

use App\Models\Appointments\Patient;
use Illuminate\Foundation\Events\Dispatchable;

final class PatientRegistered
{
    use Dispatchable;

    public function __construct(public readonly Patient $patient) {}
}
