<?php

declare(strict_types=1);

namespace App\Models\Identity;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * Sanctum token with a UUID key and the issuing IP / user agent. `last_used_at` is written at most once a minute.
 *
 * @property ?string $ip_address
 * @property ?string $user_agent
 */
class PersonalAccessToken extends SanctumToken
{
    use HasUuids;

    private const LAST_USED_THROTTLE_SECONDS = 60;

    protected static function booted(): void
    {
        static::creating(function (self $token): void {
            if (! app()->runningInConsole() || app()->runningUnitTests()) {
                $token->ip_address ??= request()->ip();
                $token->user_agent ??= mb_substr((string) request()->userAgent(), 0, 512) ?: null;
            }
        });

        // Sanctum touches last_used_at on every request; skip writes within the throttle window.
        static::updating(function (self $token): ?bool {
            $previous = $token->getOriginal('last_used_at');

            if ($token->getDirty() === ['last_used_at' => $token->last_used_at] && $previous !== null
                && $token->last_used_at !== null && $token->last_used_at->diffInSeconds($previous, true) < self::LAST_USED_THROTTLE_SECONDS) {
                return false;
            }

            return null;
        });
    }
}
