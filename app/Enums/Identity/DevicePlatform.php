<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Mobile platform of a push device.
 */
enum DevicePlatform: string implements HasLabel
{
    use EnumHelpers;

    case Android = 'android';
    case Ios = 'ios';
}
