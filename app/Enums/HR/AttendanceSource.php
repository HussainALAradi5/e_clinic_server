<?php

declare(strict_types=1);

namespace App\Enums\HR;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * How an attendance row was captured.
 */
enum AttendanceSource: string implements HasLabel
{
    use EnumHelpers;

    case Manual = 'manual';
    case Web = 'web';
    case Device = 'device';
}
