<?php

declare(strict_types=1);

namespace App\Enums\Payments;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * A fee is charged (a line on the patient's invoice) or waived (taken off, with who and why).
 */
enum AppointmentFeeStatus: string implements HasLabel
{
    use EnumHelpers;

    case Charged = 'charged';
    case Waived = 'waived';
}
