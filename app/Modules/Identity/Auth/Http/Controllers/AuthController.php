<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Http\Controllers;

use App\Modules\Identity\Auth\Contracts\AuthServiceInterface;
use App\Modules\Identity\Auth\Data\AuthTokenData;
use App\Modules\Identity\Auth\Http\Requests\ChangePasswordRequest;
use App\Modules\Identity\Auth\Http\Requests\ConfirmTwoFactorRequest;
use App\Modules\Identity\Auth\Http\Requests\DisableTwoFactorRequest;
use App\Modules\Identity\Auth\Http\Requests\ForgotPasswordRequest;
use App\Modules\Identity\Auth\Http\Requests\LoginRequest;
use App\Modules\Identity\Auth\Http\Requests\ResetPasswordRequest;
use App\Modules\Identity\Auth\Http\Requests\TwoFactorChallengeRequest;
use App\Modules\Identity\Auth\Http\Resources\SessionResource;
use App\Modules\Identity\Roles\Contracts\RoleServiceInterface;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Http\Concerns\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

        return $this->accepted(__('passwords.sent'));
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
        return $this->ok(SessionResource::make($data->user, $data->access, $data->token, $data->expiresAt));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
