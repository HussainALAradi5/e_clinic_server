<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Matches Carbon dayOfWeek (0 = Sunday).
 */
enum DayOfWeek: int implements HasLabel
{
    use EnumHelpers;

    case Sunday = 0;
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
}
