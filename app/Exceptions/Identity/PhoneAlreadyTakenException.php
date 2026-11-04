<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\ConflictException;

/**
 * Another account holder already uses this mobile number (patients without an account may share one, e.g. children).
 */
final class PhoneAlreadyTakenException extends ConflictException
{
    protected string $errorCode = 'USER_PHONE_TAKEN';
}
