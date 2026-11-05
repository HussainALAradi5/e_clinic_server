<?php

declare(strict_types=1);

namespace App\Listeners\Identity;

use App\Enums\Identity\PresenceStatus;
use App\Events\Identity\UserLoggedIn;
use App\Events\Identity\UserLoggedOut;
use App\Interfaces\Identity\AuthServiceInterface;

/**
 * Signing in makes me Online; signing out makes me Offline once no device of mine is still signed in
 * (signing out on the computer does not take me offline on my phone).
 */
final class TrackPresence
{
    public function __construct(private readonly AuthServiceInterface $auth) {}

    public function signedIn(UserLoggedIn $event): void
    {
        $this->auth->setPresence($event->user, PresenceStatus::Online);
    }

    public function signedOut(UserLoggedOut $event): void
    {
        if ($event->allDevices || $event->user->tokens()->doesntExist()) {
            $this->auth->setPresence($event->user, PresenceStatus::Offline);
        }
    }
}
