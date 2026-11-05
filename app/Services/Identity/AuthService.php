<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Dtos\Identity\AuthTokenData;
use App\Dtos\Identity\ChangePasswordData;
use App\Dtos\Identity\LoginData;
use App\Dtos\Identity\ResetPasswordData;
use App\Dtos\Identity\TwoFactorSetupData;
use App\Enums\Identity\LoginFailureReason;
use App\Enums\Identity\PresenceStatus;
use App\Enums\Identity\UserStatus;
use App\Events\Identity\LoginFailed;
use App\Events\Identity\PasswordChanged;
use App\Events\Identity\PasswordReset;
use App\Events\Identity\UserLoggedIn;
use App\Events\Identity\UserLoggedOut;
use App\Exceptions\Identity\AccountInactiveException;
use App\Exceptions\Identity\AccountLockedException;
use App\Exceptions\Identity\CurrentPasswordMismatchException;
use App\Exceptions\Identity\InvalidCredentialsException;
use App\Exceptions\Identity\InvalidPasswordResetCodeException;
use App\Exceptions\Identity\InvalidPasswordResetTokenException;
use App\Exceptions\Identity\InvalidTwoFactorCodeException;
use App\Exceptions\Identity\PasswordReuseException;
use App\Exceptions\Identity\TwoFactorAlreadyEnabledException;
use App\Exceptions\Identity\TwoFactorRequiredException;
use App\Interfaces\Identity\AuthServiceInterface;
use App\Interfaces\Identity\RoleServiceInterface;
use App\Models\Identity\LoginAttempt;
use App\Models\Identity\PasswordHistory;
use App\Models\Identity\PersonalAccessToken;
use App\Models\Identity\TrustedDevice;
use App\Models\Identity\User;
use App\Notifications\Identity\PasswordChangedNotification;
use App\Notifications\Identity\PasswordResetCodeNotification;
use App\Notifications\Identity\ResetPasswordNotification;
use App\Utilities\Identity\PersonEmailUserProvider;
use App\Utilities\Identity\TwoFactorAuthenticator;
use App\Utilities\Shared\CacheKeys;
use App\Utilities\Shared\ClinicClock;
use Database\Seeders\DemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class AuthService implements AuthServiceInterface
{
    /** Hash checked when the email is unknown, so both paths take the same time (no user enumeration). */
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$OFM1ckwzMDdYVDREd2ZhVw$grvNJDaver86Go+FFajr2ZwqeaBplh1mEc5gJZj/DXg';

    public function __construct(
        private readonly RoleServiceInterface $roles,
        private readonly TwoFactorAuthenticator $twoFactor,
        private readonly ClinicClock $clock,
    ) {}

    public function login(LoginData $data): AuthTokenData
    {
        $email = $data->normalisedEmail();
        $limiter = CacheKeys::loginAttempts($email, $data->ip);

        if (RateLimiter::tooManyAttempts($limiter, Config::integer('clinic.auth.max_failed_logins'))) {
            $this->recordAttempt($data, null, LoginFailureReason::TooManyAttempts);
            throw new AccountLockedException(context: ['retry_after' => RateLimiter::availableIn($limiter)]);
        }

        $user = PersonEmailUserProvider::findByEmail($email);

        if ($user === null || $user->password === null) {
            Hash::check($data->password, $user->password ?? self::DUMMY_HASH);
            $this->failLogin($data, $user, LoginFailureReason::InvalidCredentials, $limiter);
        }

        if (! Hash::check($data->password, (string) $user->password)) {
            $this->failLogin($data, $user, LoginFailureReason::InvalidCredentials, $limiter);
        }

        if ($user->status === UserStatus::Locked) {
            $this->recordAttempt($data, $user, LoginFailureReason::AccountLocked);
            throw new AccountLockedException;
        }

        if ($user->status !== UserStatus::Active) {
            $this->recordAttempt($data, $user, LoginFailureReason::AccountInactive);
            throw new AccountInactiveException;
        }

        if (Hash::needsRehash((string) $user->password)) {
            $user->forceFill(['password' => $data->password])->save();
        }

        // A device trusted after an earlier code skips the challenge (password still required).
        if ($user->hasTwoFactorEnabled() && ! TrustedDevice::recognises($user, $data->trustedDevice)) {
            $challenge = Str::random(64);
            Cache::put(
                CacheKeys::twoFactorChallenge($challenge),
                ['user_id' => $user->id, 'ip' => $data->ip, 'user_agent' => $data->userAgent, 'attempts' => 0],
                now()->addMinutes(Config::integer('clinic.auth.two_factor_challenge_minutes')),
            );

            throw new TwoFactorRequiredException(context: ['challenge_token' => $challenge]);
        }

        RateLimiter::clear($limiter);

        return $this->issueToken($user, $data->deviceName, $data->ip, $data->userAgent);
    }

    public function verifyTwoFactorChallenge(string $challengeToken, string $code, string $deviceName, bool $rememberDevice = false): AuthTokenData
    {
        $key = CacheKeys::twoFactorChallenge($challengeToken);

        /** @var array{user_id: string, ip: string, user_agent: ?string, attempts: int}|null $challenge */
        $challenge = Cache::get($key);
        $user = $challenge === null ? null : User::query()->with('person')->find($challenge['user_id']);

        if ($challenge === null || $user === null || ! $user->isActive()) {
            throw new InvalidTwoFactorCodeException;
        }

        if (! $this->consumeTwoFactorCode($user, $code)) {
            $attempts = $challenge['attempts'] + 1;
            $attempts >= Config::integer('clinic.auth.two_factor_max_attempts')
                ? Cache::forget($key)
                : Cache::put($key, [...$challenge, 'attempts' => $attempts], now()->addMinutes(Config::integer('clinic.auth.two_factor_challenge_minutes')));

            $this->recordAttempt(new LoginData((string) $user->email(), '', $deviceName, $challenge['ip'], $challenge['user_agent']), $user, LoginFailureReason::Invalid2FaCode);
            LoginFailed::dispatch((string) $user->email(), LoginFailureReason::Invalid2FaCode, $user);

            throw new InvalidTwoFactorCodeException;
        }

        Cache::forget($key);
        RateLimiter::clear(CacheKeys::loginAttempts(Str::lower((string) $user->email()), $challenge['ip']));

        $session = $this->issueToken($user, $deviceName, $challenge['ip'], $challenge['user_agent']);

        return $rememberDevice
            ? new AuthTokenData($session->token, $session->expiresAt, $session->user, $session->access, TrustedDevice::issue($user, $deviceName, $challenge['ip'], $challenge['user_agent']))
            : $session;
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        UserLoggedOut::dispatch($user);
    }

    public function logoutAllDevices(User $user): int
    {
        $revoked = (int) $user->tokens()->delete();
        TrustedDevice::forget($user);

        UserLoggedOut::dispatch($user, $revoked, true);

        return $revoked;
    }

    public function me(User $user): User
    {
        return $user->loadMissing(['person', 'roles']);
    }

    public function setPresence(User $user, PresenceStatus $status): User
    {
        if ($user->presence !== $status) {
            $user->forceFill(['presence' => $status, 'presence_at' => $this->clock->now()])->save();
        }

        return $user;
    }

    public function changePassword(User $user, ChangePasswordData $data): void
    {
        if ($user->password === null || ! Hash::check($data->currentPassword, $user->password)) {
            throw new CurrentPasswordMismatchException;
        }

        $this->assertNotReused($user, $data->password);

        $current = $user->currentAccessToken();
        $keep = $current instanceof PersonalAccessToken ? $current->getKey() : null;

        DB::transaction(function () use ($user, $data, $keep): void {
            $user->forceFill(['password' => $data->password])->save();
            PasswordHistory::remember($user);
            $user->tokens()->when($keep !== null, fn ($q) => $q->whereKeyNot($keep))->delete();
            TrustedDevice::forget($user);
        });

        $user->notify(new PasswordChangedNotification);
        PasswordChanged::dispatch($user);
    }

    /**
     * Emails a 6-digit code (10 minutes, 5 tries). Unknown or locked emails get the same answer and no mail,
     * so the endpoint never reveals who has an account.
     */
    public function forgotPassword(string $email): void
    {
        $user = PersonEmailUserProvider::findByEmail($email);

        if ($user === null || $user->status === UserStatus::Locked) {
            return;
        }

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        Cache::put(CacheKeys::passwordResetCode($email), [
            'user_id' => $user->id,
            'hash' => hash_hmac('sha256', $code, Config::string('app.key')),
            'attempts' => 0,
        ], now()->addMinutes(Config::integer('clinic.auth.password_code_minutes')));

        $user->notify(new PasswordResetCodeNotification($code));
    }

    /**
     * Checks the emailed code; returns a one-time reset token for POST /auth/reset-password.
     * The code works once; too many wrong tries cancel it.
     */
    public function verifyPasswordResetCode(string $email, string $code): string
    {
        $key = CacheKeys::passwordResetCode($email);
        $user = PersonEmailUserProvider::findByEmail($email);

        /** @var array{user_id: string, hash: string, attempts: int}|null $pending */
        $pending = Cache::get($key);
        $valid = $user !== null && (
            ($pending !== null && $pending['user_id'] === $user->id && hash_equals($pending['hash'], hash_hmac('sha256', trim($code), Config::string('app.key'))))
            || $this->isDemoTwoFactorCode($user, trim($code))
        );

        if (! $valid) {
            if ($pending !== null) {
                $attempts = $pending['attempts'] + 1;
                $attempts >= Config::integer('clinic.auth.password_code_max_attempts')
                    ? Cache::forget($key)
                    : Cache::put($key, [...$pending, 'attempts' => $attempts], now()->addMinutes(Config::integer('clinic.auth.password_code_minutes')));
            }

            throw new InvalidPasswordResetCodeException;
        }

        Cache::forget($key);

        return $this->broker()->createToken($user);
    }

    public function sendInvitation(User $user): void
    {
        $user->notify(new ResetPasswordNotification($this->broker()->createToken($user), (string) $user->email(), invitation: true));
    }

    public function resetPassword(ResetPasswordData $data): void
    {
        $resetUser = null;

        $status = $this->broker()->reset(
            ['email' => $data->email, 'password' => $data->password, 'token' => $data->token],
            function (User $user, string $password) use (&$resetUser): void {
                $this->assertNotReused($user, $password);

                DB::transaction(function () use ($user, $password): void {
                    $user->forceFill([
                        'password' => $password,
                        'email_verified_at' => $user->email_verified_at ?? $this->clock->now(),
                        'status' => in_array($user->status, [UserStatus::Locked, UserStatus::PendingVerification], true) ? UserStatus::Active : $user->status,
                        'locked_at' => null,
                    ])->save();
                    PasswordHistory::remember($user);
                    $user->tokens()->delete();
                    TrustedDevice::forget($user);
                });

                $resetUser = $user;
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET || ! $resetUser instanceof User) {
            throw new InvalidPasswordResetTokenException;
        }

        $this->clearLoginThrottle($resetUser);
        $resetUser->notify(new PasswordChangedNotification);
        PasswordReset::dispatch($resetUser);
    }

    public function enableTwoFactor(User $user): TwoFactorSetupData
    {
        if ($user->hasTwoFactorEnabled()) {
            throw new TwoFactorAlreadyEnabledException;
        }

        $secret = $this->twoFactor->generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();

        $url = $this->twoFactor->otpauthUrl((string) $user->email(), $secret);

        return new TwoFactorSetupData($this->twoFactor->qrCodeSvg($url), $url);
    }

    public function confirmTwoFactor(User $user, string $code): array
    {
        if ($user->hasTwoFactorEnabled()) {
            throw new TwoFactorAlreadyEnabledException;
        }
        if ($user->two_factor_secret === null || ! $this->twoFactor->verify($user->two_factor_secret, $code)) {
            throw new InvalidTwoFactorCodeException;
        }

        $codes = $this->twoFactor->recoveryCodes();
        $user->forceFill(['two_factor_confirmed_at' => $this->clock->now(), 'two_factor_recovery_codes' => $codes])->save();

        return $codes;
    }

    public function disableTwoFactor(User $user, string $password): void
    {
        if ($user->password === null || ! Hash::check($password, $user->password)) {
            throw new CurrentPasswordMismatchException;
        }

        if ($user->requiresTwoFactor()) {
            throw new AuthorizationException(__('errors.AUTH_2FA_REQUIRED'));
        }

        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        TrustedDevice::forget($user);
    }

    public function clearLoginThrottle(User $user): void
    {
        $email = Str::lower((string) $user->email());
        $ips = LoginAttempt::query()->where('email', $email)->where('created_at', '>=', now()->subMinutes(Config::integer('clinic.auth.lockout_minutes')))
            ->distinct()->pluck('ip_address');

        foreach ($ips as $ip) {
            RateLimiter::clear(CacheKeys::loginAttempts($email, (string) $ip));
        }
    }

    private function issueToken(User $user, string $deviceName, string $ip, ?string $userAgent): AuthTokenData
    {
        $expiresAt = $this->clock->now()->addHours($user->tokenLifetimeHours());

        $token = DB::transaction(function () use ($user, $deviceName, $ip, $userAgent, $expiresAt) {
            $user->forceFill(['last_login_at' => $this->clock->now(), 'last_login_ip' => $ip])->save();

            $token = $user->createToken($deviceName, ['*'], $expiresAt);
            $token->accessToken->forceFill(['ip_address' => $ip, 'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 512) : null])->save();

            return $token;
        });

        $this->recordAttempt(new LoginData((string) $user->email(), '', $deviceName, $ip, $userAgent), $user);
        UserLoggedIn::dispatch($user, $deviceName);

        return new AuthTokenData($token->plainTextToken, $expiresAt, $this->me($user), $this->roles->effectiveAccess($user));
    }

    /** TOTP code, or a one-time recovery code (consumed). */
    private function consumeTwoFactorCode(User $user, string $code): bool
    {
        $code = trim($code);

        if ($user->two_factor_secret !== null && $this->twoFactor->verify($user->two_factor_secret, $code)) {
            return true;
        }

        if ($this->isDemoTwoFactorCode($user, $code)) {
            return true;
        }

        $codes = $user->two_factor_recovery_codes ?? [];
        $index = array_search(Str::lower($code), $codes, true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return true;
    }

    /** Seeded demo accounts accept a fixed code outside production (config clinic.auth.demo_two_factor_code). */
    private function isDemoTwoFactorCode(User $user, string $code): bool
    {
        $demoCode = (string) Config::get('clinic.auth.demo_two_factor_code');

        return $demoCode !== ''
            && ! app()->isProduction()
            && Str::endsWith(Str::lower((string) $user->email()), DemoSeeder::EMAIL_DOMAIN)
            && hash_equals($demoCode, $code);
    }

    private function assertNotReused(User $user, string $password): void
    {
        if (PasswordHistory::wasUsed($user, $password)) {
            throw new PasswordReuseException;
        }
    }

    private function failLogin(LoginData $data, ?User $user, LoginFailureReason $reason, string $limiter): never
    {
        RateLimiter::hit($limiter, Config::integer('clinic.auth.lockout_minutes') * 60);
        $this->recordAttempt($data, $user, $reason);
        LoginFailed::dispatch($data->normalisedEmail(), $reason, $user);

        throw new InvalidCredentialsException;
    }

    private function recordAttempt(LoginData $data, ?User $user, ?LoginFailureReason $failure = null): void
    {
        LoginAttempt::query()->create([
            'user_id' => $user?->id,
            'email' => $data->normalisedEmail(),
            'ip_address' => $data->ip,
            'user_agent' => $data->userAgent !== null ? mb_substr($data->userAgent, 0, 512) : null,
            'successful' => $failure === null,
            'failure_reason' => $failure,
        ]);
    }

    private function broker(): PasswordBroker
    {
        return Password::broker();
    }
}
