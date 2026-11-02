<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Account (login) lifecycle status, independent of employment status.
 */
enum UserStatus: string implements HasLabel
{
    use EnumHelpers;

    case Active = 'active';
    case Inactive = 'inactive';
    case Locked = 'locked';
    case PendingVerification = 'pending_verification';
}
