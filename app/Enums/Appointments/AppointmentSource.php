<?php

declare(strict_types=1);

namespace App\Enums\Appointments;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Channel the booking came from.
 */
enum AppointmentSource: string implements HasLabel
{
    use EnumHelpers;

    case WalkIn = 'walk_in';
    case Phone = 'phone';
    case Portal = 'portal';
    case Referral = 'referral';
}
