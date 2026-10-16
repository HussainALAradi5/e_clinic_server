<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Listeners;

use App\Modules\Identity\Auth\Contracts\AuthServiceInterface;
use App\Modules\Identity\Users\Events\UserCreated;

/**
 * New account without a password → "set your password" email (reset-token flow).
 */
final class SendAccountInvitation
{
    public function __construct(private readonly AuthServiceInterface $auth) {}

    public function handle(UserCreated $event): void
    {
        if ($event->invited) {
            $this->auth->sendInvitation($event->user);
        }
    }
}
