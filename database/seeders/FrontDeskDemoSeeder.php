<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Appointments\AppointmentSource;
use App\Enums\Appointments\AppointmentStatus;
use App\Enums\Appointments\AppointmentType;
use App\Enums\Appointments\BloodGroup;
use App\Enums\Appointments\CancelledBy;
use App\Enums\Appointments\RoomType;
use App\Enums\HR\EmploymentType;
use App\Enums\Payments\ServiceCategory;
use App\Enums\Reference\CompanyType;
use App\Modules\Accounting\Accounts\Models\Account;
use App\Modules\Appointments\Appointments\Models\Appointment;
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
use App\Modules\Reference\Companies\Models\Company;
use App\Shared\Support\ClinicClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Demo data of the front desk (Phase 2) — NEVER in production. Idempotent: run it alone on an existing demo database
 * (`php artisan db:seed --class=FrontDeskDemoSeeder`) or through DemoSeeder.
 * Departments, an employee record for every demo staff account, specialties, a price list, rooms, the two demo
 * doctors with weekly schedules (Sunday–Thursday), patient files and a week of appointments around today.
 */
final class FrontDeskDemoSeeder extends Seeder
{
    /** code => [name, parent code] */
    private const DEPARTMENTS = [
        'ADMIN' => ['Administration', null],
        'CLIN' => ['Clinical services', null],
        'OPD' => ['Outpatient clinics', 'CLIN'],
        'NURS' => ['Nursing', 'CLIN'],
        'PHYSIO' => ['Physiotherapy', 'CLIN'],
        'PHARM' => ['Pharmacy', 'CLIN'],
        'DESK' => ['Front desk', 'ADMIN'],
        'FIN' => ['Finance', 'ADMIN'],
        'HR' => ['Human resources', 'ADMIN'],
    ];

    /** demo email => [department, job title, salary] */
    private const STAFF = [
        'admin@demo.eclinic.bh' => ['ADMIN', 'Clinic administrator', '1400'],
        'reception@demo.eclinic.bh' => ['DESK', 'Receptionist', '450'],
        'doctor@demo.eclinic.bh' => ['OPD', 'Family physician', '2600'],
        'doctor2@demo.eclinic.bh' => ['OPD', 'Paediatrician', '2800'],
        'nurse@demo.eclinic.bh' => ['NURS', 'Staff nurse', '700'],
        'pharmacist@demo.eclinic.bh' => ['PHARM', 'Pharmacist', '1100'],
        'hr@demo.eclinic.bh' => ['HR', 'HR manager', '1300'],
        'accountant@demo.eclinic.bh' => ['FIN', 'Accountant', '1200'],
        'cashier@demo.eclinic.bh' => ['DESK', 'Cashier', '420'],
    ];

    private const SPECIALTIES = [
        'GP' => 'General practice', 'PED' => 'Paediatrics', 'IM' => 'Internal medicine', 'DERM' => 'Dermatology',
        'ORTHO' => 'Orthopaedics', 'CARD' => 'Cardiology', 'ENT' => 'Ear, nose and throat', 'PT' => 'Physiotherapy',
    ];

    /** code => [name, category, price, revenue account code, specialty] */
    private const SERVICES = [
        'CONS-GP' => ['General practice consultation', ServiceCategory::Consultation, '15.000', '4100', 'GP'],
        'FU-GP' => ['General practice follow-up', ServiceCategory::Consultation, '8.000', '4100', 'GP'],
        'CONS-PED' => ['Paediatric consultation', ServiceCategory::Consultation, '20.000', '4100', 'PED'],
        'FU-PED' => ['Paediatric follow-up', ServiceCategory::Consultation, '10.000', '4100', 'PED'],
        'CONS-DERM' => ['Dermatology consultation', ServiceCategory::Consultation, '25.000', '4100', 'DERM'],
        'PROC-DRESS' => ['Wound dressing', ServiceCategory::Procedure, '6.000', '4200', null],
        'PROC-NEB' => ['Nebuliser session', ServiceCategory::Procedure, '5.000', '4200', null],
        'PROC-IM' => ['Injection (nurse)', ServiceCategory::Procedure, '4.000', '4200', null],
        'PROC-LASER' => ['Laser session', ServiceCategory::Procedure, '20.000', '4200', 'DERM'],
        'LAB-CBC' => ['Complete blood count', ServiceCategory::Laboratory, '7.500', '4300', null],
        'LAB-HBA1C' => ['HbA1c', ServiceCategory::Laboratory, '9.000', '4300', null],
        'XR-CHEST' => ['Chest X-ray', ServiceCategory::Radiology, '18.000', '4400', null],
        'VAC-FLU' => ['Influenza vaccine', ServiceCategory::Vaccination, '12.000', '4200', null],
    ];

    /** code => [name, type, floor, department] */
    private const ROOMS = [
        'R-101' => ['Consultation 1', RoomType::Consultation, '1', 'OPD'],
        'R-102' => ['Consultation 2', RoomType::Consultation, '1', 'OPD'],
        'R-103' => ['Consultation 3', RoomType::Consultation, '1', 'OPD'],
        'PT-1' => ['Physio gym', RoomType::Physical, '2', 'PHYSIO'],
        'TR-1' => ['Treatment room', RoomType::TreatmentRoom, '1', 'NURS'],
        'X-1' => ['X-ray', RoomType::Xray, 'G', 'CLIN'],
        'LAB-1' => ['Sample collection', RoomType::Laboratory, 'G', 'CLIN'],
        'US-1' => ['Ultrasound', RoomType::Ultrasound, 'G', 'CLIN'],
        'PR-1' => ['Procedure room', RoomType::Procedure, '1', 'NURS'],
        'VAC-1' => ['Vaccination', RoomType::Vaccination, '1', 'NURS'],
        'PH-1' => ['Pharmacy', RoomType::Pharmacy, 'G', 'PHARM'],
    ];

    public function run(ClinicClock $clock): void
    {
        if (app()->isProduction() && ! config('app.seed_demo')) {
            return;
        }
        $this->call(ChartOfAccountsSeeder::class);
        $admin = Person::query()->where('email', 'admin@demo.eclinic.bh')->first();
        if ($admin === null) {
            return; // DemoSeeder creates the accounts first.
        }
        Auth::setUser(User::query()->findOrFail($admin->id));

        $departments = $this->departments();
        $this->employees($departments);
        $specialties = $this->specialties();
        $services = $this->services($specialties);
        $rooms = $this->rooms($departments);
        $doctors = $this->doctors($specialties, $services, $rooms, $clock);
        $patients = $this->patients();
        $this->appointments($doctors, $patients, $clock);

        Auth::forgetUser();
    }

    /** @return array<string, Department> */
    private function departments(): array
    {
        /** @var array<string, Department> $made */
        $made = [];
        foreach (self::DEPARTMENTS as $code => [$name, $parent]) {
            // Parents are listed before their children.
            $made[$code] = Department::query()->firstOrCreate(['code' => $code], ['name' => $name, 'parent_id' => $parent !== null ? ($made[$parent] ?? null)?->id : null]);
        }

        return $made;
    }

    /** @param  array<string, Department>  $departments */
    private function employees(array $departments): void
    {
        foreach (self::STAFF as $email => [$department, $title, $salary]) {
            $person = Person::query()->where('email', $email)->first();
            if ($person === null || Employee::query()->whereKey($person->id)->exists()) {
                continue;
            }
            $employee = new Employee([
                'department_id' => $departments[$department]->id,
                'job_title' => $title,
                'employment_type' => EmploymentType::FullTime,
                'hire_date' => '2024-09-01',
                'work_start_time' => '08:00',
                'work_end_time' => '16:00',
                'weekly_off_days' => [5, 6],
                'basic_salary' => $salary,
                'bank_name' => 'National Bank of Bahrain',
                'iban' => 'BH67NBOB000012'.fake()->numerify('########'),
            ]);
            $employee->id = $person->id;
            $employee->save();
        }
        $departments['HR']->forceFill(['manager_id' => Person::query()->where('email', 'hr@demo.eclinic.bh')->value('id')])->save();
    }

    /** @return array<string, Specialty> */
    private function specialties(): array
    {
        $made = [];
        foreach (self::SPECIALTIES as $code => $name) {
            $made[$code] = Specialty::query()->firstOrCreate(['code' => $code], ['name' => $name]);
        }

        return $made;
    }

    /**
     * @param  array<string, Specialty>  $specialties
     * @return array<string, BillableService>
     */
    private function services(array $specialties): array
    {
        $made = [];
        foreach (self::SERVICES as $code => [$name, $category, $price, $account, $specialty]) {
            $made[$code] = BillableService::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'category' => $category,
                'price' => $price,
                'tax_rate' => '0',
                'revenue_account_id' => Account::query()->where('code', $account)->value('id'),
                'specialty_id' => $specialty !== null ? $specialties[$specialty]->id : null,
            ]);
        }

        return $made;
    }

    /**
     * @param  array<string, Department>  $departments
     * @return array<string, Room>
     */
    private function rooms(array $departments): array
    {
        /** @var array<string, Room> $made */
        $made = [];
        foreach (self::ROOMS as $code => [$name, $type, $floor, $department]) {
            $made[$code] = Room::query()->firstOrCreate(['code' => $code], [
                'name' => $name, 'type' => $type, 'floor' => $floor, 'capacity' => 1, 'department_id' => $departments[$department]->id,
            ]);
        }
        ($made['R-103'] ?? null)?->forceFill(['is_in_service' => false, 'out_of_service_reason' => 'Air conditioning repair'])->save();

        return $made;
    }

    /**
     * Dr. Omar (GP): Sun–Thu 08:00–13:00 and 16:00–20:00. Dr. Sara (paediatrics): Sun–Thu 09:00–15:00, 20-minute slots.
     *
     * @param  array<string, Specialty>  $specialties
     * @param  array<string, BillableService>  $services
     * @param  array<string, Room>  $rooms
     * @return list<Doctor>
     */
    private function doctors(array $specialties, array $services, array $rooms, ClinicClock $clock): array
    {
        $plans = [
            'doctor@demo.eclinic.bh' => ['GP', 'CONS-GP', 'FU-GP', 'R-101', 'Consultant', 15, [['08:00', '13:00'], ['16:00', '20:00']]],
            'doctor2@demo.eclinic.bh' => ['PED', 'CONS-PED', 'FU-PED', 'R-102', 'Specialist', 20, [['09:00', '15:00']]],
        ];
        $doctors = [];
        foreach ($plans as $email => [$specialty, $consultation, $followUp, $room, $title, $slot, $blocks]) {
            $id = Person::query()->where('email', $email)->value('id');
            if ($id === null || ! Employee::query()->whereKey($id)->exists()) {
                continue;
            }
            $doctors[] = Doctor::query()->firstOrCreate(['id' => $id], [
                'specialty_id' => $specialties[$specialty]->id,
                'license_number' => 'NHRA-'.fake()->unique()->numerify('######'),
                'license_expiry_date' => $clock->today()->addYears(2),
                'title' => $title,
                'consultation_service_id' => $services[$consultation]->id,
                'follow_up_service_id' => $services[$followUp]->id,
                'default_slot_minutes' => $slot,
                'default_room_id' => $rooms[$room]->id,
                'bio' => 'Demo doctor profile.',
            ]);
            if (! ProviderSchedule::query()->where('provider_id', $id)->exists()) {
                foreach ([0, 1, 2, 3, 4] as $day) {
                    foreach ($blocks as [$start, $end]) {
                        ProviderSchedule::query()->create(['provider_id' => $id, 'day_of_week' => $day, 'start_time' => "{$start}:00", 'end_time' => "{$end}:00", 'effective_from' => $clock->today()->subMonths(6)]);
                    }
                }
            }
        }

        return $doctors;
    }

    /** @return list<Patient> */
    private function patients(): array
    {
        $insurers = Company::query()->where('type', CompanyType::Insurer)->pluck('id')->all();
        $candidates = Person::query()->whereDoesntHave('user')->orWhere('email', 'patient@demo.eclinic.bh')->orderBy('code')->limit(31)->get();
        $groups = BloodGroup::cases();

        foreach ($candidates as $i => $person) {
            Patient::query()->firstOrCreate(['id' => $person->id], [
                'blood_group' => $groups[$i % count($groups)],
                'allergies' => $i % 5 === 0 ? 'Penicillin' : null,
                'chronic_conditions' => $i % 7 === 0 ? 'Type 2 diabetes' : null,
                'emergency_contact_name' => fake()->name(),
                'emergency_contact_phone' => '+9733'.fake()->numerify('#######'),
                'emergency_contact_relation' => fake()->randomElement(['Spouse', 'Parent', 'Sibling', 'Child']),
                'insurance_company_id' => $insurers !== [] && $i % 3 === 0 ? $insurers[$i % count($insurers)] : null,
                'insurance_policy_number' => $i % 3 === 0 ? 'POL-'.fake()->numerify('######') : null,
                'insurance_expiry_date' => $i % 3 === 0 ? CarbonImmutable::today()->addMonths(8) : null,
                'insurance_coverage_percent' => $i % 3 === 0 ? '80' : null,
            ]);
        }

        return array_values(Patient::query()->get()->all());
    }

    /**
     * The previous working day, today (when the clinic is open) and the next three working days, keyed by their
     * offset in working days (-1, 0, 1 …) — the clinic is closed on Friday and Saturday.
     *
     * @return array<int, CarbonImmutable>
     */
    private function days(CarbonImmutable $today): array
    {
        $open = fn (CarbonImmutable $d): bool => ! in_array($d->dayOfWeek, [5, 6], true);
        $days = [];
        $before = $today->subDay();
        while (! $open($before)) {
            $before = $before->subDay();
        }
        $days[-1] = $before;
        if ($open($today)) {
            $days[0] = $today;
        }
        for ($d = $today->addDay(), $n = 1; $n <= 3; $d = $d->addDay()) {
            if ($open($d)) {
                $days[$n++] = $d;
            }
        }

        return $days;
    }

    /**
     * The previous working day (completed / no-show), today (a full front-desk board) and the next working days.
     *
     * @param  list<Doctor>  $doctors
     * @param  list<Patient>  $patients
     */
    private function appointments(array $doctors, array $patients, ClinicClock $clock): void
    {
        if ($doctors === [] || $patients === [] || Appointment::query()->exists()) {
            return;
        }
        $today = $clock->today();
        $nowTime = $clock->toClinic($clock->now())->format('H:i:s');
        $p = 0;

        foreach ($this->days($today) as $offset => $date) {
            foreach ($doctors as $doctor) {
                $slot = $doctor->default_slot_minutes;
                $start = CarbonImmutable::parse($date->toDateString().' 09:00');
                for ($n = 0; $n < 6; $n++, $start = $start->addMinutes($slot * 2)) {
                    $patient = $patients[$p++ % count($patients)];
                    $from = $start->format('H:i:s');
                    $status = match (true) {
                        $offset < 0 => $n === 4 ? AppointmentStatus::NoShow : AppointmentStatus::Completed,
                        $offset === 0 && $from < $nowTime => [AppointmentStatus::Completed, AppointmentStatus::InProgress, AppointmentStatus::CheckedIn][$n % 3],
                        $offset === 0 => $n === 5 ? AppointmentStatus::Pending : AppointmentStatus::Confirmed,
                        default => $n === 3 ? AppointmentStatus::Pending : AppointmentStatus::Confirmed,
                    };
                    if (Appointment::query()->overlapping($date, $from, $start->addMinutes($slot)->format('H:i:s'))->where('patient_id', $patient->id)->exists()) {
                        continue;
                    }
                    Appointment::query()->create([
                        'patient_id' => $patient->id,
                        'provider_id' => $doctor->id,
                        'type' => $n === 2 ? AppointmentType::FollowUp : AppointmentType::Consultation,
                        'scheduled_date' => $date,
                        'start_time' => $from,
                        'end_time' => $start->addMinutes($slot)->format('H:i:s'),
                        'room_id' => $doctor->default_room_id,
                        'billable_service_id' => $n === 2 ? $doctor->follow_up_service_id : $doctor->consultation_service_id,
                        'status' => $status,
                        'is_attended' => in_array($status, [AppointmentStatus::CheckedIn, AppointmentStatus::InProgress, AppointmentStatus::Completed], true),
                        'source' => $status === AppointmentStatus::Pending ? AppointmentSource::Portal : fake()->randomElement([AppointmentSource::Phone, AppointmentSource::WalkIn]),
                        'reason' => fake()->randomElement(['Fever and cough', 'Routine check-up', 'Back pain', 'Skin rash', 'Follow-up of blood tests', 'Vaccination advice']),
                    ]);
                }
            }
        }

        // One cancelled booking so the filters have something to show.
        Appointment::query()->where('status', AppointmentStatus::Confirmed)->orderByDesc('scheduled_date')->first()
            ?->forceFill(['status' => AppointmentStatus::Cancelled, 'cancelled_by' => CancelledBy::Patient, 'cancellation_reason' => 'Travelling', 'cancelled_at' => now()])->save();
    }
}
