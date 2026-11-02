<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\DomainException;

/**
 * Account locked by an administrator or after too many failed logins.
 */
final class AccountLockedException extends DomainException
{
    protected int $status = 423;

    protected string $errorCode = 'AUTH_ACCOUNT_LOCKED';
}
