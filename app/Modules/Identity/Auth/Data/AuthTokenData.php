<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Data;

use App\Modules\Identity\Roles\Data\EffectiveAccessData;
use App\Modules\Identity\Users\Models\User;
use Carbon\CarbonImmutable;

/**
 * Successful sign-in: plain bearer token (returned once), its expiry, the user and their effective access.
 */
final readonly class AuthTokenData
{
    public function __construct(
        public string $token,
        public CarbonImmutable $expiresAt,
        public User $user,
        public EffectiveAccessData $access,
    ) {}
}
