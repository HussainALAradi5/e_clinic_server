<?php

declare(strict_types=1);

namespace App\Enums\Appointments;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Kind of booking. treatment_session = one session of a treatment plan (provider may be medical staff); all others need a doctor.
 */
enum AppointmentType: string implements HasLabel
{
    use EnumHelpers;

    case Consultation = 'consultation';
    case FollowUp = 'follow_up';
    case Procedure = 'procedure';
    case Emergency = 'emergency';
    case Teleconsultation = 'teleconsultation';
    case TreatmentSession = 'treatment_session';
}
