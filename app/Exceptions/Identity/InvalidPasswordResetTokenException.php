<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * Reset token is wrong, used or older than 60 minutes.
 */
final class InvalidPasswordResetTokenException extends BusinessValidationException
{
    protected string $errorCode = 'AUTH_RESET_TOKEN_INVALID';
}
