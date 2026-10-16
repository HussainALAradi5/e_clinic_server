<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * TOTP / recovery code is invalid or the challenge expired.
 */
final class InvalidTwoFactorCodeException extends BusinessValidationException
{
    protected string $errorCode = 'AUTH_2FA_INVALID';
}
