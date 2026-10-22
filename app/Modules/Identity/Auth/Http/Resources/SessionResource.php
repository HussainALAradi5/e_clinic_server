<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Http\Resources;

use App\Modules\Identity\Roles\Data\EffectiveAccessData;
use App\Modules\Identity\Users\Http\Resources\UserResource;
use App\Modules\Identity\Users\Models\User;
use Carbon\CarbonImmutable;

/**
 * Sign-in / `GET /auth/me` payload: the user plus effective roles and permissions (and the token on sign-in).
 */
final class SessionResource
{
    /**
     * @return array<string, mixed>
     */
    public static function make(User $user, EffectiveAccessData $access, ?string $token = null, ?CarbonImmutable $expiresAt = null, ?string $trustedDevice = null): array
    {
        return array_filter([
            'token' => $token,
            'token_type' => $token !== null ? 'Bearer' : null,
            'expires_at' => $expiresAt?->toIso8601String(),
            'trusted_device_token' => $trustedDevice,
            'user' => (new UserResource($user))->resolve(request()),
            ...$access->toArray(),
        ], fn (mixed $value): bool => $value !== null);
    }
}
