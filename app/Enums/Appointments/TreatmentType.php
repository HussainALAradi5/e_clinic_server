<?php

declare(strict_types=1);

namespace App\Enums\Appointments;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Kind of treatment plan; the default session length comes from config appointments.treatment_session_minutes.
 */
enum TreatmentType: string implements HasLabel
{
    use EnumHelpers;

    case Physiotherapy = 'physiotherapy';
    case Rehabilitation = 'rehabilitation';
    case WoundCare = 'wound_care';
    case Injection = 'injection';
    case Infusion = 'infusion';
    case Dialysis = 'dialysis';
    case RespiratoryTherapy = 'respiratory_therapy';
    case Laser = 'laser';
    case Dental = 'dental';
    case Other = 'other';
}
