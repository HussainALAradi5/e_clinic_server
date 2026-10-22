<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Contracts;

use App\Modules\Identity\Auth\Data\AuthTokenData;
use App\Modules\Identity\Auth\Data\ChangePasswordData;
use App\Modules\Identity\Auth\Data\LoginData;
use App\Modules\Identity\Auth\Data\ResetPasswordData;
use App\Modules\Identity\Auth\Data\TwoFactorSetupData;
use App\Modules\Identity\Users\Models\User;

interface AuthServiceInterface
{
    /** Authenticates email + password and issues a Sanctum token, or throws a 2FA challenge. */
    public function login(LoginData $data): AuthTokenData;

    /** Completes a login that required 2FA (TOTP code or one-time recovery code); optionally trusts this device. */
    public function verifyTwoFactorChallenge(string $challengeToken, string $code, string $deviceName, bool $rememberDevice = false): AuthTokenData;

    /** Revokes the token used for the current request. */
    public function logout(User $user): void;

    /** Revokes every token of the user; returns the number revoked. */
    public function logoutAllDevices(User $user): int;

    /** The authenticated user with person and roles. */
    public function me(User $user): User;

    /** Changes the password of the signed-in user (other sessions are revoked). */
    public function changePassword(User $user, ChangePasswordData $data): void;

    /** Starts the password reset flow; silent for unknown emails (no user enumeration). */
    public function forgotPassword(string $email): void;

    /** Emailed 6-digit code → one-time reset token for resetPassword(). */
    public function verifyPasswordResetCode(string $email, string $code): string;

    /** Emails a "set your password" link to a new account. */
    public function sendInvitation(User $user): void;

    /** Sets a new password with an emailed token. */
    public function resetPassword(ResetPasswordData $data): void;

    /** Generates a TOTP secret and returns the otpauth URI / QR SVG (inactive until confirmed). */
    public function enableTwoFactor(User $user): TwoFactorSetupData;

    /**
     * Confirms 2FA with a first valid code and returns the one-time recovery codes.
     *
     * @return list<string>
     */
    public function confirmTwoFactor(User $user, string $code): array;

    /** Disables 2FA after password re-confirmation (not allowed for roles where it is mandatory). */
    public function disableTwoFactor(User $user, string $password): void;

    /** Clears the failed-login limiter of an account (unlock). */
    public function clearLoginThrottle(User $user): void;
}
