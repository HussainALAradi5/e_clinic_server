<?php

declare(strict_types=1);

namespace App\Enums\Appointments;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Where a booking sits in time, for colouring and filtering lists: still ahead, today, done and gone, or missed
 * (the patient did not come). Worked out from the date and the status, never stored.
 */
enum VisitTiming: string implements HasLabel
{
    use EnumHelpers;

    case Coming = 'coming';
    case Today = 'today';
    case Past = 'past';
    case Missed = 'missed';
}
