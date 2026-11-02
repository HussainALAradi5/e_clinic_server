<?php

declare(strict_types=1);

namespace App\Models\Identity;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * A browser the user trusts for two-factor sign-in ("Don't ask again on this device"). Only the SHA-256 of the
 * token is stored; the plain token lives in that browser and is sent back with the next login.
 *
 * @property string $id
 * @property string $user_id
 * @property string $token_hash
 * @property string $device_name
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable $expires_at
 */
class TrustedDevice extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['last_used_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }

    /** Trusts the device for `clinic.auth.trusted_device_days`; returns the plain token (shown once). */
    public static function issue(User $user, string $deviceName, ?string $ip, ?string $userAgent): string
    {
        $token = Str::random(64);

        static::query()->where('user_id', $user->id)->where('expires_at', '<', now())->delete();
        static::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'device_name' => mb_substr($deviceName, 0, 100),
            'ip_address' => $ip,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 512) : null,
            'expires_at' => now()->addDays(Config::integer('clinic.auth.trusted_device_days')),
        ]);

        return $token;
    }

    /** True when the token is a live trusted device of this user (and marks it used). */
    public static function recognises(User $user, ?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        return static::query()
            ->where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->update(['last_used_at' => now()]) > 0;
    }

    /** Forgets every trusted device of the user: the next sign-in asks for a code again. */
    public static function forget(User $user): void
    {
        static::query()->where('user_id', $user->id)->delete();
    }
}
