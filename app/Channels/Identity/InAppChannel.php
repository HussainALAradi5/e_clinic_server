<?php

declare(strict_types=1);

namespace App\Channels\Identity;

use App\Interfaces\Identity\NotificationChannelDriver;
use App\Interfaces\Identity\WebPushServiceInterface;
use App\Models\Identity\Notification;

/**
 * In-app inbox: the row itself is the message, so it is delivered as soon as it is due. It is also pushed to the
 * person's browsers while their phone and browser notifications are on.
 */
final class InAppChannel implements NotificationChannelDriver
{
    public function __construct(private readonly WebPushServiceInterface $push) {}

    public function send(Notification $notification): ?string
    {
        $this->push->queueFor($notification);

        return null;
    }

    public function deliversImmediately(): bool
    {
        return true;
    }
}
