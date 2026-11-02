<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\ConflictException;

/**
 * 2FA is already confirmed for this account.
 */
final class TwoFactorAlreadyEnabledException extends ConflictException
{
    protected string $errorCode = 'AUTH_2FA_ALREADY_ENABLED';
}
