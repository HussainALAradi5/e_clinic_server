<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Listeners;

use App\Modules\Identity\Auth\Contracts\AuthServiceInterface;
use App\Modules\Identity\Users\Events\UserCreated;
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
