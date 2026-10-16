<?php

declare(strict_types=1);

namespace App\Modules\Identity\Users\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * Another person already uses this email.
 */
final class EmailAlreadyTakenException extends ConflictException
{
    protected string $errorCode = 'USER_EMAIL_TAKEN';
}
