<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * A privileged role uses an endpoint protected by `ensure.2fa` without confirmed 2FA (403).
 */
final class TwoFactorEnrollmentRequiredException extends ForbiddenActionException
{
    protected string $errorCode = 'AUTH_2FA_REQUIRED';
}
