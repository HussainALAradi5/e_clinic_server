<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Data;

use App\Modules\Identity\Roles\Data\EffectiveAccessData;
use App\Modules\Identity\Users\Models\User;
use Carbon\CarbonImmutable;

/**
 * Successful sign-in: plain bearer token (returned once), its expiry, the user, their effective access and, after
 * a remembered 2FA code, the trusted-device token.
 */
final readonly class AuthTokenData
{
    public function __construct(
        public string $token,
        public CarbonImmutable $expiresAt,
        public User $user,
        public EffectiveAccessData $access,
        /** Plain trusted-device token, when the user chose "Don't ask again on this device". */
        public ?string $trustedDevice = null,
    ) {}
}
