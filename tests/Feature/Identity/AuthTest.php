<?php

use App\Enums\Identity\LoginFailureReason;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserStatus;
use App\Modules\Identity\Audit\Models\AuditLog;
use App\Modules\Identity\Auth\Models\LoginAttempt;
use App\Modules\Identity\Auth\Notifications\PasswordChangedNotification;
use App\Modules\Identity\Auth\Notifications\ResetPasswordNotification;
use App\Modules\Identity\Auth\Support\TwoFactorAuthenticator;
use App\Modules\Identity\Users\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

const STRONG_PASSWORD = 'N3w-Secure!Pass';

function login(User $user, string $password = 'password'): TestResponse
{
    return test()->postJson('/api/v1/auth/login', ['email' => $user->email(), 'password' => $password, 'device_name' => 'tests']);
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('signs in with email and password and returns the session', function () {
    $user = User::factory()->withRole(RoleName::Receptionist->value)->create();

    $response = login($user)->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.two_factor.required', false)
        ->assertJsonPath('data.roles', ['receptionist']);

    expect($response->json('data.token'))->toBeString()
        ->and($response->json('data.permissions'))->toContain('patients.read_all', 'patients.read_own')
        ->and($response->json('data.user'))->not->toHaveKey('password')
        ->and($user->refresh()->last_login_at)->not->toBeNull()
        ->and(LoginAttempt::query()->where('successful', true)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'login')->where('user_id', $user->id)->exists())->toBeTrue();

    // The token works, expires after 12 h (staff) and is matched case-insensitively by email.
    $this->withToken($response->json('data.token'))->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.user.id', $user->id);
    expect(now()->diffInHours($user->tokens()->first()->expires_at))->toBeGreaterThan(11.9);
});

it('rejects wrong credentials with the same generic error for unknown emails', function () {
    $user = User::factory()->create();

    login($user, 'wrong')->assertStatus(401)->assertJsonPath('error_code', 'AUTH_INVALID_CREDENTIALS');
    $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'x', 'device_name' => 'tests'])
        ->assertStatus(401)->assertJsonPath('error_code', 'AUTH_INVALID_CREDENTIALS');

    expect(LoginAttempt::query()->where('failure_reason', LoginFailureReason::InvalidCredentials)->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'login_failed')->count())->toBe(2);
});

it('locks the email + IP after five failures in fifteen minutes', function () {
    $user = User::factory()->create();
    $this->withoutMiddleware(ThrottleRequests::class);

    foreach (range(1, 5) as $_) {
        login($user, 'wrong')->assertStatus(401);
    }

    login($user)->assertStatus(423)->assertJsonPath('error_code', 'AUTH_ACCOUNT_LOCKED');
});

it('refuses locked and inactive accounts', function (UserStatus $status, int $http, string $code) {
    $user = User::factory()->status($status)->create();

    login($user)->assertStatus($http)->assertJsonPath('error_code', $code);
})->with([
    'locked' => [UserStatus::Locked, 423, 'AUTH_ACCOUNT_LOCKED'],
    'inactive' => [UserStatus::Inactive, 403, 'AUTH_ACCOUNT_INACTIVE'],
    'pending' => [UserStatus::PendingVerification, 403, 'AUTH_ACCOUNT_INACTIVE'],
]);

it('asks for a TOTP code when 2FA is enabled and accepts a valid code or a recovery code once', function () {
    $user = User::factory()->withTwoFactor()->create();
    $totp = app(TwoFactorAuthenticator::class);

    $challenge = login($user)->assertStatus(401)->assertJsonPath('error_code', 'AUTH_2FA_REQUIRED')->json('meta.challenge_token');
    expect($challenge)->toBeString();

    $this->postJson('/api/v1/auth/two-factor/challenge', ['challenge_token' => $challenge, 'code' => '000000', 'device_name' => 'tests'])
        ->assertStatus(422)->assertJsonPath('error_code', 'AUTH_2FA_INVALID');
    $this->postJson('/api/v1/auth/two-factor/challenge', ['challenge_token' => $challenge, 'code' => $totp->currentCode($user->two_factor_secret), 'device_name' => 'tests'])
        ->assertOk()->assertJsonStructure(['data' => ['token', 'user', 'permissions']]);

    // Challenge tokens are single use; a recovery code works once.
    $this->postJson('/api/v1/auth/two-factor/challenge', ['challenge_token' => $challenge, 'code' => '123456', 'device_name' => 'tests'])->assertStatus(422);
    $second = login($user)->json('meta.challenge_token');
    $this->postJson('/api/v1/auth/two-factor/challenge', ['challenge_token' => $second, 'recovery_code' => 'aaaaa-11111', 'device_name' => 'tests'])->assertOk();
    expect($user->refresh()->two_factor_recovery_codes)->toBe(['bbbbb-22222']);
});

it('logs out the current token or every device', function () {
    $user = User::factory()->create();
    $first = login($user)->json('data.token');
    $second = login($user)->json('data.token');

    $this->withToken($first)->postJson('/api/v1/auth/logout')->assertNoContent();
    expect($user->tokens()->count())->toBe(1);

    app('auth')->forgetGuards();
    $this->withToken($second)->postJson('/api/v1/auth/logout-all')->assertOk()->assertJsonPath('data.revoked', 1);
    expect($user->tokens()->count())->toBe(0);
});

it('changes the password, keeps the current session and blocks reuse', function () {
    Notification::fake();
    $user = User::factory()->create();
    $current = login($user)->json('data.token');
    login($user);

    $this->withToken($current)->putJson('/api/v1/auth/password', ['current_password' => 'nope', 'password' => STRONG_PASSWORD, 'password_confirmation' => STRONG_PASSWORD])
        ->assertStatus(422)->assertJsonPath('error_code', 'AUTH_CURRENT_PASSWORD_WRONG');
    $this->withToken($current)->putJson('/api/v1/auth/password', ['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_FAILED');

    $this->withToken($current)->putJson('/api/v1/auth/password', ['current_password' => 'password', 'password' => STRONG_PASSWORD, 'password_confirmation' => STRONG_PASSWORD])
        ->assertNoContent();

    expect($user->tokens()->count())->toBe(1);
    Notification::assertSentTo($user, PasswordChangedNotification::class);

    // Back to a previous password is refused.
    $this->withToken($current)->putJson('/api/v1/auth/password', ['current_password' => STRONG_PASSWORD, 'password' => 'Pass-word-123!', 'password_confirmation' => 'Pass-word-123!'])->assertNoContent();
    $this->withToken($current)->putJson('/api/v1/auth/password', ['current_password' => 'Pass-word-123!', 'password' => STRONG_PASSWORD, 'password_confirmation' => STRONG_PASSWORD])
        ->assertStatus(422)->assertJsonPath('error_code', 'AUTH_PASSWORD_REUSED');
});

it('emails a reset link without revealing whether the email exists and resets with the token', function () {
    Notification::fake();
    $user = User::factory()->status(UserStatus::PendingVerification)->create();
    login($user);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.com'])->assertAccepted();
    $this->postJson('/api/v1/auth/forgot-password', ['email' => strtoupper((string) $user->email())])->assertAccepted();

    $token = null;
    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $n) use (&$token) {
        parse_str((string) parse_url($n->url(), PHP_URL_QUERY), $query);
        $token = $query['token'];

        return str_starts_with($n->url(), 'http://localhost:4200/reset-password?');
    });

    $this->postJson('/api/v1/auth/reset-password', ['token' => 'bad', 'email' => $user->email(), 'password' => STRONG_PASSWORD, 'password_confirmation' => STRONG_PASSWORD])
        ->assertStatus(422)->assertJsonPath('error_code', 'AUTH_RESET_TOKEN_INVALID');
    $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'email' => $user->email(), 'password' => 'password1A!x', 'password_confirmation' => 'password1A!x'])
        ->assertNoContent();

    expect($user->refresh()->status)->toBe(UserStatus::Active)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Password::broker()->tokenExists($user, $token))->toBeFalse();
    login($user, 'password1A!x')->assertOk();
});

it('enrols, confirms and disables TOTP 2FA', function () {
    $user = User::factory()->create();
    $token = login($user)->json('data.token');
    $totp = app(TwoFactorAuthenticator::class);

    $setup = $this->withToken($token)->postJson('/api/v1/auth/two-factor/enable')->assertOk();
    expect($setup->json('data.otpauth_url'))->toStartWith('otpauth://totp/')
        ->and($setup->json('data.qr_code_svg'))->toStartWith('<svg');

    $secret = $user->refresh()->two_factor_secret;
    $this->withToken($token)->postJson('/api/v1/auth/two-factor/confirm', ['code' => '000000'])->assertStatus(422);
    $codes = $this->withToken($token)->postJson('/api/v1/auth/two-factor/confirm', ['code' => $totp->currentCode($secret)])->assertOk()->json('data.recovery_codes');
    expect($codes)->toHaveCount(8);

    $this->withToken($token)->postJson('/api/v1/auth/two-factor/enable')->assertStatus(409)->assertJsonPath('error_code', 'AUTH_2FA_ALREADY_ENABLED');
    $this->withToken($token)->deleteJson('/api/v1/auth/two-factor', ['password' => 'wrong'])->assertStatus(422)->assertJsonPath('error_code', 'AUTH_CURRENT_PASSWORD_WRONG');
    $this->withToken($token)->deleteJson('/api/v1/auth/two-factor', ['password' => 'password'])->assertNoContent();
    expect($user->refresh()->hasTwoFactorEnabled())->toBeFalse();
});

it('keeps 2FA mandatory for privileged roles', function () {
    $admin = User::factory()->withRole(RoleName::Admin->value)->withTwoFactor()->create();
    $plain = User::factory()->withRole(RoleName::Admin->value)->create();

    Sanctum::actingAs($admin);
    $this->deleteJson('/api/v1/auth/two-factor', ['password' => 'password'])->assertForbidden();

    Sanctum::actingAs($plain);
    $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.user.two_factor', ['enabled' => false, 'required' => true]);
    $this->getJson('/api/v1/roles')->assertForbidden()->assertJsonPath('error_code', 'AUTH_2FA_REQUIRED');
});
