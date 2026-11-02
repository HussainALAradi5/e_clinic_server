<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * The "forgot password" code is wrong, used, expired or was tried too many times.
 */
final class InvalidPasswordResetCodeException extends BusinessValidationException
{
    protected string $errorCode = 'AUTH_RESET_CODE_INVALID';
}
