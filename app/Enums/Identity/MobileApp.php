<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Which Flutter app flavor registered the device.
 */
enum MobileApp: string implements HasLabel
{
    use EnumHelpers;

    case Patient = 'patient';
    case Staff = 'staff';
}
