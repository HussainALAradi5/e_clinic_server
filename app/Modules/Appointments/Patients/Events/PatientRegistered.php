<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Patients\Events;

use App\Modules\Appointments\Patients\Models\Patient;
use Illuminate\Foundation\Events\Dispatchable;

final class PatientRegistered
{
    use Dispatchable;

    public function __construct(public readonly Patient $patient) {}
}
