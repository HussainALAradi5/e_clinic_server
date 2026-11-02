<?php

declare(strict_types=1);

namespace App\Enums\HR;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Result of one employee's working day (one attendance row per employee per day).
 */
enum AttendanceStatus: string implements HasLabel
{
    use EnumHelpers;

    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case HalfDay = 'half_day';
    case OnLeave = 'on_leave';
    case Holiday = 'holiday';
    case Weekend = 'weekend';

    /** Counts as a worked day (late is still present). */
    public function isWorked(): bool
    {
        return in_array($this, [self::Present, self::Late, self::HalfDay], true);
    }
}
