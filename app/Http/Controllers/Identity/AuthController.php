<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Dtos\Identity\AuthTokenData;
use App\Enums\Identity\PresenceStatus;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Identity\ChangePasswordRequest;
use App\Http\Requests\Identity\ConfirmTwoFactorRequest;
use App\Http\Requests\Identity\DisableTwoFactorRequest;
use App\Http\Requests\Identity\ForgotPasswordRequest;
use App\Http\Requests\Identity\LoginRequest;
use App\Http\Requests\Identity\ResetPasswordRequest;
use App\Http\Requests\Identity\TwoFactorChallengeRequest;
use App\Http\Requests\Identity\VerifyResetCodeRequest;
use App\Http\Resources\Identity\SessionResource;
use App\Interfaces\Identity\AuthServiceInterface;
use App\Interfaces\Identity\RoleServiceInterface;
use App\Models\Identity\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class AuthController
{
    use ApiResponse;

    public function __construct(
        private readonly AuthServiceInterface $auth,
        private readonly RoleServiceInterface $roles,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        return $this->session($this->auth->login($request->toData()));
    }

    public function twoFactorChallenge(TwoFactorChallengeRequest $request): JsonResponse
    {
        return $this->session($this->auth->verifyTwoFactorChallenge(
            $request->string('challenge_token')->toString(),
            $request->code(),
            $request->string('device_name')->toString(),
            $request->boolean('remember_device'),
        ));
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout($this->user($request));

        return $this->noContent();
    }

    public function logoutAll(Request $request): JsonResponse
    {
        return $this->ok(['revoked' => $this->auth->logoutAllDevices($this->user($request))]);
    }

    /** Switches my Online / Offline status by hand: `status` online|offline. */
    public function presence(Request $request): JsonResponse
    {
        $request->validate(['status' => ['required', Rule::enum(PresenceStatus::class)]]);
        $user = $this->auth->setPresence($this->user($request), PresenceStatus::from($request->string('status')->toString()));

        return $this->ok(['presence' => $user->presence->value]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->auth->me($this->user($request));

        return $this->ok(SessionResource::make($user, $this->roles->effectiveAccess($user)));
    }

    public function changePassword(ChangePasswordRequest $request): Response
    {
        $this->auth->changePassword($this->user($request), $request->toData());

        return $this->noContent();
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->auth->forgotPassword($request->string('email')->toString());

        return $this->accepted(__('passwords.code_sent'));
    }

    /** The emailed code → a one-time token for POST /auth/reset-password. */
    public function verifyResetCode(VerifyResetCodeRequest $request): JsonResponse
    {
        $token = $this->auth->verifyPasswordResetCode($request->string('email')->toString(), $request->string('code')->toString());

        return $this->ok(['token' => $token]);
    }

    public function resetPassword(ResetPasswordRequest $request): Response
    {
        $this->auth->resetPassword($request->toData());

        return $this->noContent();
    }

    public function enableTwoFactor(Request $request): JsonResponse
    {
        return $this->ok($this->auth->enableTwoFactor($this->user($request))->toArray());
    }

    public function confirmTwoFactor(ConfirmTwoFactorRequest $request): JsonResponse
    {
        return $this->ok(['recovery_codes' => $this->auth->confirmTwoFactor($this->user($request), $request->string('code')->toString())]);
    }

    public function disableTwoFactor(DisableTwoFactorRequest $request): Response
    {
        $this->auth->disableTwoFactor($this->user($request), $request->string('password')->toString());

        return $this->noContent();
    }

    private function session(AuthTokenData $data): JsonResponse
    {
        return $this->ok(SessionResource::make($data->user, $data->access, $data->token, $data->expiresAt, $data->trustedDevice));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
