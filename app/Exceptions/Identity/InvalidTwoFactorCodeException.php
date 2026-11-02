<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * TOTP / recovery code is invalid or the challenge expired.
 */
final class InvalidTwoFactorCodeException extends BusinessValidationException
{
    protected string $errorCode = 'AUTH_2FA_INVALID';
}
