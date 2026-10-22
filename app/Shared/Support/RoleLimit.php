<?php

declare(strict_types=1);

namespace App\Shared\Support;

use App\Modules\Identity\Users\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Best limit of a user's roles from a `role => limit` config map (discount %, approval amount …).
 * `null` in the map means unlimited; no matching role gives zero.
 */
final class RoleLimit
{
    /**
     * @param  array<string, int|string|null>  $limits
     * @return ?BigDecimal null = unlimited
     */
    public static function best(?Authenticatable $user, array $limits): ?BigDecimal
    {
        if (! $user instanceof User) {
            return BigDecimal::zero();
        }

        $best = BigDecimal::zero();
        foreach ($user->getRoleNames() as $role) {
            if (! array_key_exists($role, $limits)) {
                continue;
            }
            if ($limits[$role] === null) {
                return null;
            }
            $best = BigDecimal::max($best, BigDecimal::of((string) $limits[$role]));
        }

        return $best;
    }
}
