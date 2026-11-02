<?php

declare(strict_types=1);

namespace App\Listeners\Identity;

use App\Events\Identity\UserCreated;
use App\Interfaces\Identity\AuthServiceInterface;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * New account without a password → "set your password" email (reset-token flow). Sent after the transaction
 * commits, so a rolled-back import never emails a link to an account that does not exist.
 */
final class SendAccountInvitation implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly AuthServiceInterface $auth) {}

    public function handle(UserCreated $event): void
    {
        if ($event->invited) {
            $this->auth->sendInvitation($event->user);
        }
    }
}
