<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Dtos\Identity\CreateUserData;
use App\Dtos\Identity\PersonData;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Enums\Shared\Gender;
use App\Interfaces\Identity\UserServiceInterface;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * First super admin (config clinic.super_admin ← SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD). Without SUPER_ADMIN_PASSWORD the default
 * password below is used outside production; production gets a random one, printed once.
 * 2FA is mandatory for this role: sign in, then set it up under My account › Security.
 */
class SuperAdminSeeder extends Seeder
{
    /** Local / test default — change it in `.env` (SUPER_ADMIN_PASSWORD) or after signing in. */
    public const DEFAULT_PASSWORD = 'Admin-Pass-2026!';

    public function run(UserServiceInterface $users): void
    {
        $email = Config::string('clinic.super_admin.email');

        if (User::query()->whereHas('person', fn ($q) => $q->where('email', Str::lower($email)))->exists()) {
            $this->command->info("Super admin {$email} already exists.");

            return;
        }

        $password = (string) Config::get('clinic.super_admin.password')
            ?: (app()->isProduction() ? Str::password(16) : self::DEFAULT_PASSWORD);

        $person = $users->findOrCreatePerson(new PersonData(
            firstName: 'System',
            lastName: 'Administrator',
            gender: Gender::Male,
            dateOfBirth: CarbonImmutable::parse('1990-01-01'),
            phone: '+97317000000',
            email: $email,
        ));

        $users->create($person, new CreateUserData(UserType::Admin, [RoleName::SuperAdmin->value], $password));

        $this->command->warn("Super admin: {$email} / {$password} — change the password and set up 2FA after the first sign-in.");
    }
}
