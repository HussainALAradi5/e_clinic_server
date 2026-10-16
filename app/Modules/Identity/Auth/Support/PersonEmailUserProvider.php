<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Support;

use App\Modules\Identity\Users\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Auth provider `identity`: the login email lives on persons (shared primary key), so lookups by
 * email join persons case-insensitively. Used by the password broker and Auth::attempt-style calls.
 */
final class PersonEmailUserProvider extends EloquentUserProvider
{
    public const DRIVER = 'identity';

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $email = $credentials['email'] ?? null;

        if (! is_string($email) || $email === '') {
            return null;
        }

        return self::findByEmail($email);
    }

    public static function findByEmail(string $email): ?User
    {
        return User::query()
            ->whereHas('person', fn (Builder $q) => $q->whereRaw('lower(email) = ?', [Str::lower(trim($email))]))
            ->with('person')
            ->first();
    }
}
