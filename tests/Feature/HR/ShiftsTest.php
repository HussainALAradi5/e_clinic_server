<?php

use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\Identity\Notifications\Models\Notification as AppNotification;
use App\Modules\Identity\Users\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\FrontDesk;

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->seed([CountrySeeder::class, RolesAndPermissionsSeeder::class]);
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-01 05:00:00', 'UTC'));
});

function shiftStaff(RoleName $role = RoleName::Nurse, UserType $type = UserType::MedicalStaff): Employee
{
    $employee = FrontDesk::employee($type, $role);
    if ($role === RoleName::HrManager) {
        User::query()->whereKey($employee->id)->update([
            'two_factor_secret' => encrypt((new Google2FA)->generateSecretKey(32)), 'two_factor_confirmed_at' => now(),
        ]);
    }

    return $employee;
}

function shiftAs(Employee $employee): void
{
    Sanctum::actingAs(User::query()->findOrFail($employee->id));
}

function shiftAt(string $time): void
{
    test()->travelTo(CarbonImmutable::parse("2026-11-01 {$time}", 'Asia/Bahrain')->utc());
}

function shiftNotice(Employee $employee): ?AppNotification
{
    return AppNotification::query()->where('person_id', $employee->id)->where('template', 'like', 'attendance.%')->latest('created_at')->first();
}

it('lets HR create fixed and flexible shifts with codes and put people on them; others cannot', function () {
    $hr = shiftStaff(RoleName::HrManager, UserType::Hr);
    $nurse = shiftStaff();
    $doctor = shiftStaff(RoleName::Doctor, UserType::Doctor);

    shiftAs($nurse);
    $this->getJson('/api/v1/hr/shifts')->assertForbidden();

    shiftAs($hr);
    $this->postJson('/api/v1/hr/shifts', ['name' => 'Flexi', 'type' => 'flexible', 'start_time' => '07:00', 'end_time' => '19:00'])
        ->assertUnprocessable()->assertJsonValidationErrors(['latest_start', 'required_minutes']);
    $this->postJson('/api/v1/hr/shifts', ['name' => 'Flexi', 'type' => 'flexible', 'start_time' => '07:00', 'end_time' => '12:00', 'latest_start' => '09:00', 'required_minutes' => 480])
        ->assertUnprocessable()->assertJsonValidationErrors(['required_minutes']);

    $morning = $this->postJson('/api/v1/hr/shifts', ['name' => 'Morning', 'type' => 'fixed', 'start_time' => '07:00', 'end_time' => '15:00'])
        ->assertCreated()->assertJsonPath('data.required_minutes', 480)->assertJsonPath('data.latest_start', null)->json('data');
    expect($morning['code'])->toStartWith('SFT-');
    $flexi = $this->postJson('/api/v1/hr/shifts', ['name' => 'Office flexi', 'type' => 'flexible', 'start_time' => '07:00', 'end_time' => '19:00', 'latest_start' => '10:00', 'required_minutes' => 480])
        ->assertCreated()->assertJsonPath('data.arrive_by', '10:00')->json('data');

    // Putting people on a shift sets their hours; moving them takes them off the other shift.
    $this->postJson("/api/v1/hr/shifts/{$morning['id']}/employees", ['employee_ids' => [$nurse->id, $doctor->id]])
        ->assertOk()->assertJsonCount(2, 'data.employees');
    expect(Employee::query()->find($nurse->id)->work_start_time)->toStartWith('07:00');
    $this->postJson("/api/v1/hr/shifts/{$flexi['id']}/employees", ['employee_ids' => [$doctor->id]])->assertOk()->assertJsonCount(1, 'data.employees');
    $this->getJson("/api/v1/hr/shifts/{$morning['id']}")->assertOk()->assertJsonCount(1, 'data.employees')->assertJsonPath('data.employees.0.id', $nurse->id);
    $this->getJson("/api/v1/hr/employees?filter[shift_id][eq]={$flexi['id']}")->assertOk()->assertJsonCount(1, 'data');
    $this->postJson("/api/v1/hr/shifts/{$morning['id']}/employees", ['employee_ids' => ['00000000-0000-0000-0000-000000000000']])->assertUnprocessable();

    // Changing the hours moves everyone on the shift; a shift with people cannot be deleted.
    $this->putJson("/api/v1/hr/shifts/{$morning['id']}", ['name' => 'Morning', 'type' => 'fixed', 'start_time' => '08:00', 'end_time' => '16:00'])->assertOk();
    expect(Employee::query()->find($nurse->id)->work_start_time)->toStartWith('08:00');
    $this->deleteJson("/api/v1/hr/shifts/{$morning['id']}")->assertStatus(409)->assertJsonPath('error_code', 'SHIFT_HAS_EMPLOYEES');
    $this->deleteJson("/api/v1/hr/shifts/{$morning['id']}/employees/{$nurse->id}")->assertOk()->assertJsonCount(0, 'data.employees');
    $this->deleteJson("/api/v1/hr/shifts/{$morning['id']}")->assertNoContent();
});

it('tells employees whether they were on time, late, on overtime or left early against their shift', function () {
    $hr = shiftStaff(RoleName::HrManager, UserType::Hr);
    $nurse = shiftStaff();
    $doctor = shiftStaff(RoleName::Doctor, UserType::Doctor);

    shiftAs($hr);
    $fixed = $this->postJson('/api/v1/hr/shifts', ['name' => 'Morning', 'type' => 'fixed', 'start_time' => '08:00', 'end_time' => '16:00'])->json('data.id');
    $flexi = $this->postJson('/api/v1/hr/shifts', ['name' => 'Office flexi', 'type' => 'flexible', 'start_time' => '07:00', 'end_time' => '19:00', 'latest_start' => '10:00', 'required_minutes' => 480])->json('data.id');
    $this->postJson("/api/v1/hr/shifts/{$fixed}/employees", ['employee_ids' => [$nurse->id]])->assertOk();
    $this->postJson("/api/v1/hr/shifts/{$flexi}/employees", ['employee_ids' => [$doctor->id]])->assertOk();

    // Fixed: 20 minutes late, then out an hour early.
    shiftAs($nurse);
    shiftAt('08:20');
    $this->postJson('/api/v1/hr/attendance/check-in')->assertOk()->assertJsonPath('data.late_minutes', 20);
    expect(shiftNotice($nurse)->template)->toBe('attendance.checked_in_late');
    $this->getJson('/api/v1/hr/attendance/today')->assertOk()->assertJsonPath('data.shift.name', 'Morning')->assertJsonPath('data.shift.type', 'fixed');
    shiftAt('15:00');
    $this->postJson('/api/v1/hr/attendance/check-out')->assertOk()->assertJsonPath('data.overtime_minutes', 0);
    expect(shiftNotice($nurse)->template)->toBe('attendance.checked_out_early');

    // Flexible: in at 09:30 (before the latest start, on time), 9.5 hours later = 90 minutes beyond the 8 due.
    shiftAs($doctor);
    shiftAt('09:30');
    $this->postJson('/api/v1/hr/attendance/check-in')->assertOk()->assertJsonPath('data.late_minutes', 0);
    expect(shiftNotice($doctor)->template)->toBe('attendance.checked_in_on_time');
    shiftAt('19:00');
    $this->postJson('/api/v1/hr/attendance/check-out')->assertOk()->assertJsonPath('data.overtime_minutes', 90);
    $notice = shiftNotice($doctor);
    expect($notice->template)->toBe('attendance.checked_out_overtime')
        ->and($notice->subject)->toBe('Overtime today — 1 h 30 min')
        ->and($notice->body)->toContain('Office flexi')->toContain('waiting for HR approval');
    expect(shiftNotice($nurse)->body)->toContain('1 h 00 min before the end of Morning');
});
