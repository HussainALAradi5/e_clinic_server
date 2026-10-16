<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications\Channels;

use App\Modules\Identity\Notifications\Contracts\NotificationChannelDriver;
use App\Modules\Identity\Notifications\Models\Notification;

/**
 * In-app inbox: the row itself is the message, so it is delivered as soon as it is due.
 */
final class InAppChannel implements NotificationChannelDriver
{
    public function send(Notification $notification): ?string
    {
        return null;
    }

    public function deliversImmediately(): bool
    {
        return true;
    }
}
