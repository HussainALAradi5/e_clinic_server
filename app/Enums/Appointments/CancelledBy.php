<?php

declare(strict_types=1);

namespace App\Enums\Appointments;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Who initiated a cancellation.
 */
enum CancelledBy: string implements HasLabel
{
    use EnumHelpers;

    case Patient = 'patient';
    case Clinic = 'clinic';
    case Doctor = 'doctor';
    case System = 'system';
}
