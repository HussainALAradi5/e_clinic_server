<?php

declare(strict_types=1);

namespace App\Notifications\Identity\Channels;

use App\Notifications\Identity\Contracts\NotificationChannelDriver;
use App\Notifications\Identity\Models\Notification;

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
