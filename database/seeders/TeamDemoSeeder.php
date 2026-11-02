<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Appointments\BloodGroup;
use App\Enums\Appointments\RoomType;
use App\Enums\HR\EmploymentType;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Enums\Payments\ServiceCategory;
use App\Enums\Reference\CompanyType;
use App\Modules\Accounting\Accounts\Models\Account;
use App\Modules\Appointments\Doctors\Models\Doctor;
use App\Modules\Appointments\Doctors\Models\Specialty;
use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\Appointments\Rooms\Models\Room;
use App\Modules\Appointments\Schedules\Models\ProviderSchedule;
use App\Modules\HR\Departments\Models\Department;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Identity\Users\Models\User;
use App\Modules\Payments\Catalog\Models\BillableService;
use App\Modules\Reference\Addresses\Models\Address;
use App\Modules\Reference\Companies\Models\Company;
use App\Shared\Enums\Gender;
use App\Shared\Support\ClinicClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * More of everything the clinic runs on — NEVER in production. Idempotent (skipped once doctor3 exists).
 * Four more doctors (internal medicine, dermatology, orthopaedics, cardiology) with their schedules and prices,
 * a second nurse, cashier and receptionist, two more portal patients, and 120 patient files with names, ages
 * (children included), nationalities and insurance as a Bahrain clinic sees them.
 * Runs after FrontDeskDemoSeeder and before the HR seeder, so the new staff get attendance and payslips too.
 */
final class TeamDemoSeeder extends Seeder
{
    /** email prefix => [first, last, gender, role, user type, department, job title, salary, nationality] */
    private const STAFF = [
        'doctor3' => ['Khalid', 'Al-Mahmood', Gender::Male, RoleName::Doctor, UserType::Doctor, 'OPD', 'Internist', '2900', 'BH'],
        'doctor4' => ['Huda', 'Al-Sayed', Gender::Female, RoleName::Doctor, UserType::Doctor, 'OPD', 'Dermatologist', '2700', 'BH'],
        'doctor5' => ['Rajesh', 'Menon', Gender::Male, RoleName::Doctor, UserType::Doctor, 'OPD', 'Orthopaedic surgeon', '3200', 'IN'],
        'doctor6' => ['Mona', 'Farouk', Gender::Female, RoleName::Doctor, UserType::Doctor, 'OPD', 'Cardiologist', '3400', 'EG'],
        'doctor7' => ['Noora', 'Al-Mannai', Gender::Female, RoleName::Doctor, UserType::Doctor, 'OPD', 'Dentist', '2600', 'BH'],
        'nurse2' => ['Grace', 'Dela Cruz', Gender::Female, RoleName::Nurse, UserType::MedicalStaff, 'PHYSIO', 'Physiotherapist', '850', 'PH'],
        'cashier2' => ['Ali', 'Hasan', Gender::Male, RoleName::Cashier, UserType::Cashier, 'DESK', 'Cashier', '420', 'BH'],
        'reception2' => ['Zainab', 'Abdulla', Gender::Female, RoleName::Receptionist, UserType::Receptionist, 'DESK', 'Receptionist', '450', 'BH'],
        'patient2' => ['Fatema', 'Al-Khalifa', Gender::Female, RoleName::Patient, UserType::Patient, null, null, null, 'BH'],
        'patient3' => ['Joseph', 'Thomas', Gender::Male, RoleName::Patient, UserType::Patient, null, null, null, 'IN'],
    ];

    /** email prefix => [specialty, consultation, follow-up, room, title, slot minutes, [[from, to] …]] */
    private const DOCTORS = [
        'doctor3' => ['IM', 'CONS-IM', 'FU-IM', 'R-104', 'Consultant', 20, [['08:00', '14:00']]],
        'doctor4' => ['DERM', 'CONS-DERM', 'FU-DERM', 'R-105', 'Specialist', 15, [['10:00', '13:00'], ['16:00', '19:00']]],
        'doctor5' => ['ORTHO', 'CONS-ORTHO', 'FU-ORTHO', 'R-106', 'Consultant', 20, [['08:00', '13:00']]],
        'doctor6' => ['CARD', 'CONS-CARD', 'FU-CARD', 'R-107', 'Consultant', 30, [['09:00', '15:00']]],
        // The dentist works the evening clinic in the dental room.
        'doctor7' => ['DENT', 'CONS-DENT', 'FU-DENT', 'D-1', 'Specialist', 30, [['15:00', '21:00']]],
    ];

    /** code => [name, category, price, revenue account, specialty] */
    private const SERVICES = [
        'CONS-IM' => ['Internal medicine consultation', ServiceCategory::Consultation, '18.000', '4100', 'IM'],
        'FU-IM' => ['Internal medicine follow-up', ServiceCategory::Consultation, '9.000', '4100', 'IM'],
        'FU-DERM' => ['Dermatology follow-up', ServiceCategory::Consultation, '12.000', '4100', 'DERM'],
        'CONS-ORTHO' => ['Orthopaedic consultation', ServiceCategory::Consultation, '25.000', '4100', 'ORTHO'],
        'FU-ORTHO' => ['Orthopaedic follow-up', ServiceCategory::Consultation, '12.000', '4100', 'ORTHO'],
        'CONS-CARD' => ['Cardiology consultation', ServiceCategory::Consultation, '30.000', '4100', 'CARD'],
        'FU-CARD' => ['Cardiology follow-up', ServiceCategory::Consultation, '15.000', '4100', 'CARD'],
        'LAB-LIPID' => ['Lipid profile', ServiceCategory::Laboratory, '8.000', '4300', null],
        'LAB-TSH' => ['Thyroid function (TSH)', ServiceCategory::Laboratory, '9.000', '4300', null],
        'LAB-VITD' => ['Vitamin D', ServiceCategory::Laboratory, '15.000', '4300', null],
        'LAB-UA' => ['Urinalysis', ServiceCategory::Laboratory, '4.000', '4300', null],
        'XR-KNEE' => ['Knee X-ray', ServiceCategory::Radiology, '16.000', '4400', null],
        'XR-SPINE' => ['Lumbar spine X-ray', ServiceCategory::Radiology, '20.000', '4400', null],
        'PROC-CRYO' => ['Cryotherapy (warts)', ServiceCategory::Procedure, '15.000', '4200', 'DERM'],
        'PROC-INJ' => ['Joint injection', ServiceCategory::Procedure, '30.000', '4200', 'ORTHO'],
        'CONS-DENT' => ['Dental examination', ServiceCategory::Consultation, '15.000', '4100', 'DENT'],
        'FU-DENT' => ['Dental follow-up', ServiceCategory::Consultation, '8.000', '4100', 'DENT'],
        'PROC-SCALE' => ['Scaling and polishing', ServiceCategory::Procedure, '25.000', '4200', 'DENT'],
        'PROC-FILL' => ['Tooth filling', ServiceCategory::Procedure, '30.000', '4200', 'DENT'],
        'XR-DENT' => ['Dental panoramic X-ray (OPG)', ServiceCategory::Radiology, '18.000', '4400', 'DENT'],
    ];

    /** code => [name, floor] (consultation rooms of the new doctors). */
    private const ROOMS = ['R-104' => ['Consultation 4', '1'], 'R-105' => ['Consultation 5', '1'], 'R-106' => ['Consultation 6', '2'], 'R-107' => ['Consultation 7', '2']];

    /** Names as the clinic's patients have them: [first names (male), first names (female), family names, nationality]. */
    private const NAMES = [
        [['Mohammed', 'Ahmed', 'Hussain', 'Abdulla', 'Yousif', 'Ebrahim', 'Jassim', 'Salman', 'Hamad', 'Isa'],
            ['Maryam', 'Fatema', 'Zahra', 'Noora', 'Sara', 'Aysha', 'Latifa', 'Hessa', 'Reem', 'Shaikha'],
            ['Al-Khalifa', 'Al-Mannai', 'Al-Zayani', 'Kanoo', 'Fakhro', 'Al-Jishi', 'Al-Aradi', 'Bucheeri', 'Al-Sitri', 'Janahi', 'Al-Mulla', 'Al-Hashimi'], 'BH'],
        [['Rahul', 'Arun', 'Vijay', 'Suresh', 'Anil', 'Faisal', 'Imran'], ['Priya', 'Anjali', 'Lakshmi', 'Divya', 'Sneha', 'Ayesha'],
            ['Nair', 'Kumar', 'Pillai', 'Sharma', 'Reddy', 'Iyer', 'Khan', 'Qureshi'], 'IN'],
        [['Jose', 'Mark', 'Paolo', 'Carlo'], ['Maria', 'Angelica', 'Kristine', 'Joy'], ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza'], 'PH'],
        [['Omar', 'Tarek', 'Mostafa', 'Karim'], ['Nadia', 'Heba', 'Rania', 'Dina'], ['Hassan', 'Abdelrahman', 'Mahmoud', 'Saleh', 'Farouk'], 'EG'],
        [['James', 'David', 'Thomas'], ['Emma', 'Sophie', 'Charlotte'], ['Smith', 'Taylor', 'Brown', 'Wilson'], 'GB'],
        [['Bilal', 'Usman', 'Hamza'], ['Sana', 'Hira', 'Amna'], ['Malik', 'Butt', 'Chaudhry', 'Raza'], 'PK'],
    ];

    public function run(ClinicClock $clock): void
    {
        if ((app()->isProduction() && ! config('app.seed_demo')) || Person::query()->where('email', 'doctor3'.DemoSeeder::EMAIL_DOMAIN)->exists()) {
            return;
        }
        $admin = User::query()->whereHas('person', fn ($q) => $q->where('email', 'admin'.DemoSeeder::EMAIL_DOMAIN))->first();
        if ($admin === null || ! Department::query()->where('code', 'OPD')->exists()) {
            return; // DemoSeeder and FrontDeskDemoSeeder run first.
        }
        Auth::setUser($admin);

        $this->staff();
        $services = $this->services();
        $this->doctors($services, $clock);
        $this->patients($clock);

        Auth::forgetUser();
    }

    private function staff(): void
    {
        $password = Hash::make(DemoSeeder::DEMO_PASSWORD);
        foreach (self::STAFF as $prefix => [$first, $last, $gender, $role, $type, $department, $title, $salary, $nationality]) {
            $person = Person::factory()->create([
                'first_name' => $first, 'last_name' => $last, 'gender' => $gender, 'email' => $prefix.DemoSeeder::EMAIL_DOMAIN,
                'nationality_code' => $nationality, 'address_id' => $this->address()->id,
                'date_of_birth' => fake()->dateTimeBetween('-60 years', '-25 years')->format('Y-m-d'),
            ]);
            User::factory()->state(['id' => $person->id, 'password' => $password, 'user_type' => $type])->withRole($role->value)->create();
            if ($department === null) {
                continue;
            }
            $employee = new Employee([
                'department_id' => Department::query()->where('code', $department)->value('id'),
                'job_title' => $title,
                'employment_type' => EmploymentType::FullTime,
                'hire_date' => fake()->dateTimeBetween('-4 years', '-6 months')->format('Y-m-d'),
                'work_start_time' => '08:00',
                'work_end_time' => '16:00',
                'weekly_off_days' => [5, 6],
                'basic_salary' => $salary,
                'bank_name' => fake()->randomElement(['National Bank of Bahrain', 'Bank of Bahrain and Kuwait', 'Ahli United Bank']),
                'iban' => 'BH67NBOB000012'.fake()->numerify('########'),
            ]);
            $employee->id = $person->id;
            $employee->save();
        }
    }

    /** @return array<string, BillableService> */
    private function services(): array
    {
        $specialties = ['IM' => 'Internal medicine', 'DERM' => 'Dermatology', 'ORTHO' => 'Orthopaedics', 'CARD' => 'Cardiology', 'DENT' => 'Dentistry'];
        foreach ($specialties as $code => $name) {
            Specialty::query()->firstOrCreate(['code' => $code], ['name' => $name]);
        }
        foreach (self::SERVICES as $code => [$name, $category, $price, $account, $specialty]) {
            BillableService::query()->firstOrCreate(['code' => $code], [
                'name' => $name, 'category' => $category, 'price' => $price, 'tax_rate' => '0',
                'revenue_account_id' => Account::query()->where('code', $account)->value('id'),
                'specialty_id' => $specialty === null ? null : Specialty::query()->where('code', $specialty)->value('id'),
            ]);
        }

        return BillableService::query()->get()->keyBy('code')->all();
    }

    /** @param  array<string, BillableService>  $services */
    private function doctors(array $services, ClinicClock $clock): void
    {
        $opd = Department::query()->where('code', 'OPD')->value('id');
        foreach (self::ROOMS as $code => [$name, $floor]) {
            Room::query()->firstOrCreate(['code' => $code], ['name' => $name, 'type' => RoomType::Consultation, 'floor' => $floor, 'capacity' => 1, 'department_id' => $opd]);
        }
        Room::query()->firstOrCreate(['code' => 'D-1'], ['name' => 'Dental room', 'type' => RoomType::Dental, 'floor' => '1', 'capacity' => 1, 'department_id' => $opd]);
        foreach (self::DOCTORS as $prefix => [$specialty, $consultation, $followUp, $room, $title, $slot, $blocks]) {
            $id = (string) Person::query()->where('email', $prefix.DemoSeeder::EMAIL_DOMAIN)->value('id');
            Doctor::query()->create([
                'id' => $id,
                'specialty_id' => Specialty::query()->where('code', $specialty)->value('id'),
                'license_number' => 'NHRA-'.fake()->unique()->numerify('######'),
                'license_expiry_date' => $prefix === 'doctor6' ? $clock->today()->addDays(40) : $clock->today()->addYears(2),
                'title' => $title,
                'consultation_service_id' => $services[$consultation]->id,
                'follow_up_service_id' => $services[$followUp]->id,
                'default_slot_minutes' => $slot,
                'default_room_id' => Room::query()->where('code', $room)->value('id'),
                'bio' => fake()->randomElement([
                    'Trained at the Royal College of Surgeons in Ireland – Bahrain; 12 years of practice.',
                    'Board certified; special interest in preventive care and chronic disease.',
                    'Fellowship in sports medicine; treats athletes and active adults.',
                    'Speaks Arabic, English and Hindi.',
                ]),
            ]);
            foreach ([0, 1, 2, 3, 4] as $day) {
                // Dr. Huda works Sunday to Wednesday; the others Sunday to Thursday.
                if ($prefix === 'doctor4' && $day === 4) {
                    continue;
                }
                foreach ($blocks as [$start, $end]) {
                    ProviderSchedule::query()->create(['provider_id' => $id, 'day_of_week' => $day, 'start_time' => "{$start}:00", 'end_time' => "{$end}:00", 'effective_from' => $clock->today()->subMonths(6)]);
                }
            }
        }
        // The second physiotherapist works mornings, like the nurse.
        $therapist = (string) Person::query()->where('email', 'nurse2'.DemoSeeder::EMAIL_DOMAIN)->value('id');
        foreach ([0, 1, 2, 3, 4] as $day) {
            ProviderSchedule::query()->create(['provider_id' => $therapist, 'day_of_week' => $day, 'start_time' => '08:00:00', 'end_time' => '14:00:00', 'effective_from' => $clock->today()->subMonths(6)]);
        }
    }

    /** The two new portal accounts and 120 patient files. */
    private function patients(ClinicClock $clock): void
    {
        $insurers = Company::query()->where('type', CompanyType::Insurer)->where('is_archived', false)->pluck('id')->all();
        $groups = BloodGroup::cases();
        $people = Person::query()->whereIn('email', ['patient2'.DemoSeeder::EMAIL_DOMAIN, 'patient3'.DemoSeeder::EMAIL_DOMAIN])->get()->all();

        for ($i = 0; $i < 120; $i++) {
            [$male, $female, $families, $nationality] = self::NAMES[$i % 3 === 0 ? 0 : $i % count(self::NAMES)];
            $gender = $i % 2 === 0 ? Gender::Female : Gender::Male;
            // About one in five is a child (paediatrics), one in six over 65.
            $age = match (true) {
                $i % 5 === 0 => fake()->numberBetween(0, 15),
                $i % 6 === 0 => fake()->numberBetween(65, 88),
                default => fake()->numberBetween(18, 64),
            };
            $people[] = Person::factory()->create([
                'first_name' => fake()->randomElement($gender === Gender::Male ? $male : $female),
                'last_name' => fake()->randomElement($families),
                'gender' => $gender,
                'date_of_birth' => $clock->today()->subYears($age)->subDays(fake()->numberBetween(0, 364))->toDateString(),
                'nationality_code' => $nationality,
                'national_id' => fake()->unique()->numerify($nationality === 'BH' ? '9#######' : '8#######'),
                'email' => $age < 16 ? null : fake()->unique()->userName().'@example.com',
                'address_id' => $i % 4 === 3 ? null : $this->address()->id,
            ]);
        }

        foreach ($people as $i => $person) {
            $insured = $insurers !== [] && $i % 3 !== 2;
            Patient::query()->create([
                'id' => $person->id,
                'blood_group' => $groups[$i % count($groups)],
                'allergies' => [null, null, null, 'Penicillin', null, 'Peanuts', null, 'Sulfa drugs', null, null, 'Latex'][$i % 11],
                'chronic_conditions' => [null, null, 'Hypertension', null, 'Type 2 diabetes', null, 'Asthma', null, null, 'Hypothyroidism'][$i % 10],
                'emergency_contact_name' => fake()->name(),
                'emergency_contact_phone' => '+9733'.fake()->numerify('#######'),
                'emergency_contact_relation' => fake()->randomElement(['Spouse', 'Parent', 'Sibling', 'Child', 'Friend']),
                'insurance_company_id' => $insured ? $insurers[$i % count($insurers)] : null,
                'insurance_policy_number' => $insured ? 'POL-'.fake()->numerify('#######') : null,
                // A few policies have expired: the desk warns before billing the insurer.
                'insurance_expiry_date' => $insured ? ($i % 13 === 0 ? $clock->today()->subMonths(2) : CarbonImmutable::today()->addMonths(fake()->numberBetween(2, 18))) : null,
                'insurance_coverage_percent' => $insured ? fake()->randomElement(['70', '80', '80', '90', '100']) : null,
            ]);
        }
    }

    private function address(): Address
    {
        return Address::query()->create([
            'country_code' => 'BH',
            'flat' => (string) fake()->numberBetween(1, 40),
            'building' => (string) fake()->numberBetween(100, 3999),
            'road' => (string) fake()->numberBetween(100, 4999),
            'block' => (string) fake()->numberBetween(300, 999),
            'area' => fake()->randomElement(['Seef', 'Juffair', 'Adliya', 'Riffa', 'Muharraq', 'Saar', 'Hamad Town', 'Isa Town', 'Budaiya', 'Sitra', 'Zinj', 'Tubli']),
        ]);
    }
}
