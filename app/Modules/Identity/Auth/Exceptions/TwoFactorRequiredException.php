<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Credentials are valid but a TOTP code is required; `meta.challenge_token` continues the login.
 */
final class TwoFactorRequiredException extends DomainException
{
    protected int $status = 401;

    protected string $errorCode = 'AUTH_2FA_REQUIRED';
}
