<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Which Flutter app flavor registered the device.
 */
enum MobileApp: string implements HasLabel
{
    use EnumHelpers;

    case Patient = 'patient';
    case Staff = 'staff';
}
