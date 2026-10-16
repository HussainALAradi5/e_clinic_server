<?php

declare(strict_types=1);

namespace Database\Factories\Identity;

use App\Enums\Identity\UserStatus;
use App\Enums\Identity\UserType;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/**
 * Login account on a new person. Default password: `password`.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    private static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => Person::factory(),
            'password' => self::$password ??= Hash::make('password'),
            'user_type' => UserType::Admin,
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ];
    }

    /**
     * @param  string|list<string>  $roles
     */
    public function withRole(string|array $roles): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole($roles));
    }

    public function type(UserType $type): static
    {
        return $this->state(fn (): array => ['user_type' => $type]);
    }

    public function status(UserStatus $status): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
            'locked_at' => $status === UserStatus::Locked ? now() : null,
        ]);
    }

    /** Confirmed TOTP 2FA with a known secret and recovery codes. */
    public function withTwoFactor(?string $secret = null): static
    {
        return $this->state(fn (): array => [
            'two_factor_secret' => $secret ?? (new Google2FA)->generateSecretKey(32),
            'two_factor_recovery_codes' => ['aaaaa-11111', 'bbbbb-22222'],
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
