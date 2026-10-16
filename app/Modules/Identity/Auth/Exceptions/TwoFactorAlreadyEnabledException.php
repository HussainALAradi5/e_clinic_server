<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * 2FA is already confirmed for this account.
 */
final class TwoFactorAlreadyEnabledException extends ConflictException
{
    protected string $errorCode = 'AUTH_2FA_ALREADY_ENABLED';
}
