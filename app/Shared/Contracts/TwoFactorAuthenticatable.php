<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * Accounts that may (or must) use TOTP two-factor authentication.
 */
interface TwoFactorAuthenticatable
{
    /** One of the account's roles makes 2FA mandatory (config `access.two_factor_roles`). */
    public function requiresTwoFactor(): bool;

    /** 2FA is set up and confirmed. */
    public function hasTwoFactorEnabled(): bool;
}
