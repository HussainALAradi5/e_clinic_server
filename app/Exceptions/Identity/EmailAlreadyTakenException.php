<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\ConflictException;

/**
 * Another person already uses this email.
 */
final class EmailAlreadyTakenException extends ConflictException
{
    protected string $errorCode = 'USER_EMAIL_TAKEN';
}
