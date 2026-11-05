<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Whether a staff member shows as available in chat: Online on sign-in, Offline once signed out everywhere,
 * and switchable by hand.
 */
enum PresenceStatus: string implements HasLabel
{
    use EnumHelpers;

    case Online = 'online';
    case Offline = 'offline';
}
