<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\Accounting\AccountType;
use App\Enums\Appointments\RoomType;
use App\Enums\HR\EmploymentType;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Enums\Payments\ServiceCategory;
use App\Models\Accounting\Account;
use App\Models\Appointments\Doctor;
use App\Models\Appointments\Patient;
use App\Models\Appointments\ProviderSchedule;
use App\Models\Appointments\Room;
use App\Models\Appointments\Specialty;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Models\Payments\BillableService;
use Carbon\CarbonImmutable;

/**
 * Builds front-desk fixtures (department, doctor with prices and a weekly schedule, rooms, patients) for tests.
 */
final class FrontDesk
{
    public static function department(): Department
    {
        return Department::query()->firstOrCreate(['code' => 'OPD'], ['name' => 'Outpatients']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function service(string $code = 'CONS-GP', string $price = '15.000', ServiceCategory $category = ServiceCategory::Consultation, array $overrides = []): BillableService
    {
        $account = Account::query()->firstOrCreate(['code' => '4100'], ['name' => 'Consultation revenue', 'type' => AccountType::Revenue, 'normal_balance' => AccountType::Revenue->normalBalance()]);

        return BillableService::query()->firstOrCreate(['code' => $code], [
            'name' => $code, 'category' => $category, 'price' => $price, 'tax_rate' => '0', 'revenue_account_id' => $account->id, ...$overrides,
        ]);
    }

    /** Medical staff (physiotherapist / nurse) working 08:00–16:00 every day from a month ago. */
    public static function therapist(): Employee
    {
        $employee = self::employee(UserType::MedicalStaff, RoleName::Nurse);
        foreach (range(0, 6) as $day) {
            ProviderSchedule::query()->create(['provider_id' => $employee->id, 'day_of_week' => $day, 'start_time' => '08:00:00', 'end_time' => '16:00:00', 'effective_from' => CarbonImmutable::today()->subMonth()]);
        }

        return $employee;
    }

    public static function specialty(): Specialty
    {
        return Specialty::query()->firstOrCreate(['code' => 'GP'], ['name' => 'General practice']);
    }

    public static function employee(UserType $type = UserType::Doctor, RoleName $role = RoleName::Doctor): Employee
    {
        $user = User::factory()->state(['user_type' => $type])->withRole($role->value)->create();

        $employee = new Employee([
            'department_id' => self::department()->id, 'job_title' => 'Physician', 'employment_type' => EmploymentType::FullTime,
            'hire_date' => '2025-01-01', 'work_start_time' => '08:00', 'work_end_time' => '16:00', 'weekly_off_days' => [5, 6], 'basic_salary' => '1000',
        ]);
        $employee->id = $user->id;
        $employee->save();

        return $employee;
    }

    /**
     * Doctor working 08:00–12:00 every weekday (15-minute slots) from today.
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function doctor(array $overrides = []): Doctor
    {
        $employee = self::employee();
        $doctor = Doctor::query()->create([
            'id' => $employee->id, 'specialty_id' => self::specialty()->id, 'license_number' => 'NHRA-'.fake()->unique()->numerify('#####'),
            'license_expiry_date' => CarbonImmutable::today()->addYear(), 'consultation_service_id' => self::service()->id,
            'follow_up_service_id' => self::service('FU-GP', '8.000')->id, 'default_slot_minutes' => 15, ...$overrides,
        ]);
        foreach (range(0, 6) as $day) {
            ProviderSchedule::query()->create(['provider_id' => $doctor->id, 'day_of_week' => $day, 'start_time' => '08:00:00', 'end_time' => '12:00:00', 'effective_from' => CarbonImmutable::today()->subMonth()]);
        }

        return $doctor;
    }

    public static function room(string $code = 'R-101', RoomType $type = RoomType::Consultation): Room
    {
        return Room::query()->create(['code' => $code, 'name' => "Room {$code}", 'type' => $type, 'capacity' => 1]);
    }

    public static function patient(): Patient
    {
        return Patient::query()->create(['id' => Person::factory()->create()->id]);
    }
}
