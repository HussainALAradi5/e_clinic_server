<?php

declare(strict_types=1);

namespace App\Listeners\Identity;

use App\Events\Identity\UserLoggedOut;
use App\Interfaces\Identity\NotificationServiceInterface;

/**
 * "Sign out of all devices" also removes push tokens, so a shared phone never receives someone else's notifications.
 */
final class ForgetDevicesOnLogoutAll
{
    public function __construct(private readonly NotificationServiceInterface $notifications) {}

    public function handle(UserLoggedOut $event): void
    {
        if ($event->allDevices) {
            $this->notifications->forgetDevices($event->user);
        }
    }
}
