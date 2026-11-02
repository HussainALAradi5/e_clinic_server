<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * The new password matches one of the last remembered passwords.
 */
final class PasswordReuseException extends BusinessValidationException
{
    protected string $errorCode = 'AUTH_PASSWORD_REUSED';
}
