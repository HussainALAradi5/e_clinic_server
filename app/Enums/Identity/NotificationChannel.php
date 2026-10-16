<?php

declare(strict_types=1);

namespace App\Enums\Identity;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Delivery channel.
 */
enum NotificationChannel: string implements HasLabel
{
    use EnumHelpers;

    case Sms = 'sms';
    case Email = 'email';
    case Whatsapp = 'whatsapp';
    case Push = 'push';
    case InApp = 'in_app';
}
