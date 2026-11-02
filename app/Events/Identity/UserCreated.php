<?php

declare(strict_types=1);

namespace App\Events\Identity;

use App\Models\Identity\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A login account was added to a person (invite email is sent when no password was set).
 */
final class UserCreated
{
    use Dispatchable;

    public function __construct(public readonly User $user, public readonly bool $invited) {}
}
