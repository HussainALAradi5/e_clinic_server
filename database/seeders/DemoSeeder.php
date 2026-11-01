<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Identity\NotificationChannel;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserStatus;
use App\Enums\Identity\UserType;
use App\Enums\Reference\CompanyType;
use App\Modules\Identity\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Identity\Users\Models\User;
use App\Modules\Reference\Addresses\Models\Address;
use App\Modules\Reference\Companies\Models\Company;
use App\Shared\Enums\Gender;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Demo / test data — NEVER run in production (DatabaseSeeder only calls it outside production or with SEED_DEMO=true).
 * One account per role (password DEMO_PASSWORD), fake patients and companies, inbox notifications.
 * The accounts are listed in e_clinic_documentation/DEMO_USERS.md — keep both in sync.
 */
final class DemoSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'Demo-Pass-2026!';

    /** Every demo account's email ends with this (AuthService accepts the demo two-factor code for them). */
    public const EMAIL_DOMAIN = '@demo.eclinic.bh';

    /** TOTP secret shared by the demo accounts whose role requires 2FA (add it to any authenticator app). */
    public const TWO_FACTOR_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    /** Single-use recovery codes of the 2FA demo accounts (an alternative to the authenticator). */
    public const RECOVERY_CODES = ['demo1-11111', 'demo2-22222', 'demo3-33333', 'demo4-44444', 'demo5-55555', 'demo6-66666', 'demo7-77777', 'demo8-88888'];

    /** @var list<array{email: string, first: string, last: string, gender: Gender, role: RoleName, type: UserType, status?: UserStatus}> */
    private const ACCOUNTS = [
        ['email' => 'admin@demo.eclinic.bh', 'first' => 'Amal', 'last' => 'Haddad', 'gender' => Gender::Female, 'role' => RoleName::Admin, 'type' => UserType::Admin],
        ['email' => 'reception@demo.eclinic.bh', 'first' => 'Rana', 'last' => 'Saleh', 'gender' => Gender::Female, 'role' => RoleName::Receptionist, 'type' => UserType::Receptionist],
        ['email' => 'doctor@demo.eclinic.bh', 'first' => 'Omar', 'last' => 'Khalil', 'gender' => Gender::Male, 'role' => RoleName::Doctor, 'type' => UserType::Doctor],
        ['email' => 'doctor2@demo.eclinic.bh', 'first' => 'Sara', 'last' => 'Nasser', 'gender' => Gender::Female, 'role' => RoleName::Doctor, 'type' => UserType::Doctor],
        ['email' => 'nurse@demo.eclinic.bh', 'first' => 'Maria', 'last' => 'Santos', 'gender' => Gender::Female, 'role' => RoleName::Nurse, 'type' => UserType::MedicalStaff],
        ['email' => 'pharmacist@demo.eclinic.bh', 'first' => 'Yusuf', 'last' => 'Ibrahim', 'gender' => Gender::Male, 'role' => RoleName::Pharmacist, 'type' => UserType::Pharmacist],
        ['email' => 'hr@demo.eclinic.bh', 'first' => 'Layla', 'last' => 'Mansour', 'gender' => Gender::Female, 'role' => RoleName::HrManager, 'type' => UserType::Hr],
        ['email' => 'accountant@demo.eclinic.bh', 'first' => 'Hassan', 'last' => 'Ali', 'gender' => Gender::Male, 'role' => RoleName::Accountant, 'type' => UserType::Accountant],
        ['email' => 'cashier@demo.eclinic.bh', 'first' => 'Fatima', 'last' => 'Jaber', 'gender' => Gender::Female, 'role' => RoleName::Cashier, 'type' => UserType::Cashier],
        ['email' => 'support@demo.eclinic.bh', 'first' => 'Ziad', 'last' => 'Karim', 'gender' => Gender::Male, 'role' => RoleName::Support, 'type' => UserType::Admin],
        ['email' => 'patient@demo.eclinic.bh', 'first' => 'Ahmed', 'last' => 'Yousif', 'gender' => Gender::Male, 'role' => RoleName::Patient, 'type' => UserType::Patient],
        ['email' => 'locked@demo.eclinic.bh', 'first' => 'Khalid', 'last' => 'Locked', 'gender' => Gender::Male, 'role' => RoleName::Receptionist, 'type' => UserType::Receptionist, 'status' => UserStatus::Locked],
        ['email' => 'inactive@demo.eclinic.bh', 'first' => 'Noor', 'last' => 'Inactive', 'gender' => Gender::Female, 'role' => RoleName::Cashier, 'type' => UserType::Cashier, 'status' => UserStatus::Inactive],
    ];

    private const COMPANIES = [
        ['Gulf Insurance Group', CompanyType::Insurer, 'BH'],
        ['Bahrain National Health Insurance', CompanyType::Insurer, 'BH'],
        ['Arabian Shield Cooperative', CompanyType::Insurer, 'SA'],
        ['Pfizer Inc.', CompanyType::Manufacturer, 'US'],
        ['Novartis AG', CompanyType::Manufacturer, 'CH'],
        ['Julphar Gulf Pharmaceutical', CompanyType::Manufacturer, 'AE'],
        ['Yiaco Medical Supplies', CompanyType::Supplier, 'BH'],
        ['Gulf Drug Distribution', CompanyType::Supplier, 'BH'],
        ['Al Borg Diagnostics', CompanyType::Laboratory, 'BH'],
        ['Royal Bahrain Hospital', CompanyType::Hospital, 'BH'],
        ['King Hamad University Hospital', CompanyType::Hospital, 'BH'],
        ['Seef Cleaning Services', CompanyType::Other, 'BH'],
    ];

    public function run(NotificationServiceInterface $notifications): void
    {
        if (app()->isProduction() && ! config('app.seed_demo')) {
            $this->command->error('DemoSeeder refused: production environment.');

            return;
        }

        $password = Hash::make(self::DEMO_PASSWORD);
        $twoFactorRoles = (array) config('access.two_factor_roles');
        $users = [];

        foreach (self::ACCOUNTS as $account) {
            if (Person::query()->where('email', $account['email'])->exists()) {
                continue;
            }
            $person = Person::factory()->create([
                'first_name' => $account['first'],
                'last_name' => $account['last'],
                'gender' => $account['gender'],
                'email' => $account['email'],
                'nationality_code' => 'BH',
                'address_id' => $this->bahrainAddress()->id,
            ]);
            $factory = User::factory()->state([
                'id' => $person->id,
                'password' => $password,
                'user_type' => $account['type'],
            ])->status($account['status'] ?? UserStatus::Active);
            if (in_array($account['role']->value, $twoFactorRoles, true)) {
                $factory = $factory->withTwoFactor(self::TWO_FACTOR_SECRET)->state(['two_factor_recovery_codes' => self::RECOVERY_CODES]);
            }
            $users[$account['email']] = $factory->withRole($account['role']->value)->create();
        }

        // Patients without an account (front-desk records), half of them Bahraini with a Bahrain address.
        Person::factory()->count(40)->create()->each(function (Person $person, int $i): void {
            $bahraini = $i % 2 === 0;
            $person->forceFill([
                'nationality_code' => $bahraini ? 'BH' : fake()->randomElement(['IN', 'PH', 'PK', 'EG', 'SA', 'GB', 'BD', 'JO']),
                'address_id' => $bahraini ? $this->bahrainAddress()->id : null,
                'national_id' => fake()->unique()->numerify('9#######'),
            ])->save();
        });

        // Companies, created by the admin so "own" rules have an owner.
        Auth::setUser($users['admin@demo.eclinic.bh'] ?? User::query()->firstOrFail());
        foreach (self::COMPANIES as $i => [$name, $type, $country]) {
            if (Company::query()->where('name', $name)->exists()) {
                continue;
            }
            $company = Company::query()->create([
                'type' => $type,
                'name' => $name,
                'country_code' => $country,
                'address_id' => $country === 'BH' ? $this->bahrainAddress()->id : null,
                'phone' => '+9731'.fake()->numerify('7######'),
                'email' => 'info@'.str($name)->slug()->limit(20, '').'.example',
                'contact_person' => fake()->name(),
                'vat_number' => $country === 'BH' ? fake()->numerify('2000########') : null,
            ]);
            if ($i === count(self::COMPANIES) - 1) {
                $company->archive();
            }
        }
        Auth::forgetUser();

        // A few unread in-app notifications so the staff bell has something to show (patients get real ones).
        foreach (array_filter($users, fn (User $u): bool => $u->user_type !== UserType::Patient) as $user) {
            $notifications->send('task.completed', $user->person, null, ['task' => 'Welcome to the demo'], null, [NotificationChannel::InApp]);
        }

        // Front desk (Phase 2): departments, employees, price list, rooms, doctors, patients and appointments.
        $this->call(FrontDeskDemoSeeder::class);
        $this->call(TeamDemoSeeder::class);
        $this->call(ClinicalDemoSeeder::class);
        $this->call(BillingDemoSeeder::class);
        $this->call(PharmacyDemoSeeder::class);
        $this->call(HrDemoSeeder::class);
        // Ten weeks of visits, results, prescriptions, payments and expenses, plus three weeks of bookings ahead.
        $this->call(HistoryDemoSeeder::class);
        // Staff chat: groups with leaders, one-to-one chats, files, a voice note, mentions and reactions.
        $this->call(ChatDemoSeeder::class);
        // Support tickets in every status, priority and category (two pages in the list).
        $this->call(SupportDemoSeeder::class);

        $this->command->info('Demo data ready — accounts in e_clinic_documentation/DEMO_USERS.md (password '.self::DEMO_PASSWORD.').');
    }

    private function bahrainAddress(): Address
    {
        return Address::query()->create([
            'country_code' => 'BH',
            'flat' => (string) fake()->numberBetween(1, 40),
            'building' => (string) fake()->numberBetween(100, 3999),
            'road' => (string) fake()->numberBetween(100, 4999),
            'block' => (string) fake()->numberBetween(300, 999),
            'area' => fake()->randomElement(['Seef', 'Juffair', 'Adliya', 'Riffa', 'Muharraq', 'Saar', 'Hamad Town', 'Isa Town', 'Budaiya']),
        ]);
    }
}
