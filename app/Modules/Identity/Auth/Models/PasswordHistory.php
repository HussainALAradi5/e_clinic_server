<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Models;

use App\Modules\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

/**
 * Previous password hashes of a user (the last N may not be reused).
 *
 * @property string $id
 * @property string $user_id
 * @property string $password_hash
 */
class PasswordHistory extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $hidden = ['password_hash'];

    /** Stores the user's current hash and keeps only the newest entries. */
    public static function remember(User $user): void
    {
        $keep = Config::integer('clinic.auth.password_history');

        static::query()->create(['user_id' => $user->id, 'password_hash' => $user->password]);

        $stale = static::query()->where('user_id', $user->id)->latest('created_at')->latest('id')->skip($keep)->take(PHP_INT_MAX)->pluck('id');
        static::query()->whereIn('id', $stale)->delete();
    }

    /** True when the plain password matches the current or one of the remembered passwords. */
    public static function wasUsed(User $user, string $plain): bool
    {
        if ($user->password !== null && Hash::check($plain, $user->password)) {
            return true;
        }

        return static::query()->where('user_id', $user->id)->latest('created_at')->latest('id')
            ->limit(Config::integer('clinic.auth.password_history'))
            ->pluck('password_hash')
            ->contains(fn (string $hash): bool => Hash::check($plain, $hash));
    }
}
