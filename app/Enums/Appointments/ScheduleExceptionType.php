<?php

declare(strict_types=1);

namespace App\Enums\Appointments;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Override of a provider's weekly schedule on a date.
 */
enum ScheduleExceptionType: string implements HasLabel
{
    use EnumHelpers;

    case DayOff = 'day_off';
    case CustomHours = 'custom_hours';
    case Holiday = 'holiday';
}
