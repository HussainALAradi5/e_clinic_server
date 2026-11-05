<?php

use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Models\HR\Employee;
use App\Models\Identity\Notification as AppNotification;
use App\Models\Identity\User;
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
    $this->getJson('/hr/shifts')->assertForbidden();

    shiftAs($hr);
    $this->postJson('/hr/shifts', ['name' => 'Flexi', 'type' => 'flexible', 'start_time' => '07:00', 'end_time' => '19:00'])
        ->assertUnprocessable()->assertJsonValidationErrors(['latest_start', 'required_minutes']);
    $this->postJson('/hr/shifts', ['name' => 'Flexi', 'type' => 'flexible', 'start_time' => '07:00', 'end_time' => '12:00', 'latest_start' => '09:00', 'required_minutes' => 480])
        ->assertUnprocessable()->assertJsonValidationErrors(['required_minutes']);

    $morning = $this->postJson('/hr/shifts', ['name' => 'Morning', 'type' => 'fixed', 'start_time' => '07:00', 'end_time' => '15:00'])
        ->assertCreated()->assertJsonPath('data.required_minutes', 480)->assertJsonPath('data.latest_start', null)->json('data');
    expect($morning['code'])->toStartWith('SFT-');
    $flexi = $this->postJson('/hr/shifts', ['name' => 'Office flexi', 'type' => 'flexible', 'start_time' => '07:00', 'end_time' => '19:00', 'latest_start' => '10:00', 'required_minutes' => 480])
        ->assertCreated()->assertJsonPath('data.arrive_by', '10:00')->json('data');

    // Putting people on a shift sets their hours; moving them takes them off the other shift.
    $this->postJson("/hr/shifts/{$morning['id']}/employees", ['employee_ids' => [$nurse->id, $doctor->id]])
        ->assertOk()->assertJsonCount(2, 'data.employees');
    expect(Employee::query()->find($nurse->id)->work_start_time)->toStartWith('07:00');
    $this->postJson("/hr/shifts/{$flexi['id']}/employees", ['employee_ids' => [$doctor->id]])->assertOk()->assertJsonCount(1, 'data.employees');
    $this->getJson("/hr/shifts/{$morning['id']}")->assertOk()->assertJsonCount(1, 'data.employees')->assertJsonPath('data.employees.0.id', $nurse->id);
    $this->getJson("/hr/employees?filter[shift_id][eq]={$flexi['id']}")->assertOk()->assertJsonCount(1, 'data');
    $this->postJson("/hr/shifts/{$morning['id']}/employees", ['employee_ids' => ['00000000-0000-0000-0000-000000000000']])->assertUnprocessable();

    // Changing the hours moves everyone on the shift; a shift with people cannot be deleted.
    $this->putJson("/hr/shifts/{$morning['id']}", ['name' => 'Morning', 'type' => 'fixed', 'start_time' => '08:00', 'end_time' => '16:00'])->assertOk();
    expect(Employee::query()->find($nurse->id)->work_start_time)->toStartWith('08:00');
    $this->deleteJson("/hr/shifts/{$morning['id']}")->assertStatus(409)->assertJsonPath('error_code', 'SHIFT_HAS_EMPLOYEES');
    $this->deleteJson("/hr/shifts/{$morning['id']}/employees/{$nurse->id}")->assertOk()->assertJsonCount(0, 'data.employees');
    $this->deleteJson("/hr/shifts/{$morning['id']}")->assertNoContent();
});

it('tells employees whether they were on time, late, on overtime or left early against their shift', function () {
    $hr = shiftStaff(RoleName::HrManager, UserType::Hr);
    $nurse = shiftStaff();
    $doctor = shiftStaff(RoleName::Doctor, UserType::Doctor);

    shiftAs($hr);
    $fixed = $this->postJson('/hr/shifts', ['name' => 'Morning', 'type' => 'fixed', 'start_time' => '08:00', 'end_time' => '16:00'])->json('data.id');
    $flexi = $this->postJson('/hr/shifts', ['name' => 'Office flexi', 'type' => 'flexible', 'start_time' => '07:00', 'end_time' => '19:00', 'latest_start' => '10:00', 'required_minutes' => 480])->json('data.id');
    $this->postJson("/hr/shifts/{$fixed}/employees", ['employee_ids' => [$nurse->id]])->assertOk();
    $this->postJson("/hr/shifts/{$flexi}/employees", ['employee_ids' => [$doctor->id]])->assertOk();

    // Fixed: 20 minutes late, then out an hour early.
    shiftAs($nurse);
    shiftAt('08:20');
    $this->postJson('/hr/attendance/check-in')->assertOk()->assertJsonPath('data.late_minutes', 20);
    expect(shiftNotice($nurse)->template)->toBe('attendance.checked_in_late');
    $this->getJson('/hr/attendance/today')->assertOk()->assertJsonPath('data.shift.name', 'Morning')->assertJsonPath('data.shift.type', 'fixed');
    shiftAt('15:00');
    $this->postJson('/hr/attendance/check-out')->assertOk()->assertJsonPath('data.overtime_minutes', 0);
    expect(shiftNotice($nurse)->template)->toBe('attendance.checked_out_early');

    // Flexible: in at 09:30 (before the latest start, on time), 9.5 hours later = 90 minutes beyond the 8 due.
    shiftAs($doctor);
    shiftAt('09:30');
    $this->postJson('/hr/attendance/check-in')->assertOk()->assertJsonPath('data.late_minutes', 0);
    expect(shiftNotice($doctor)->template)->toBe('attendance.checked_in_on_time');
    shiftAt('19:00');
    $this->postJson('/hr/attendance/check-out')->assertOk()->assertJsonPath('data.overtime_minutes', 90);
    $notice = shiftNotice($doctor);
    expect($notice->template)->toBe('attendance.checked_out_overtime')
        ->and($notice->subject)->toBe('Overtime today — 1 h 30 min')
        ->and($notice->body)->toContain('Office flexi')->toContain('waiting for HR approval');
    expect(shiftNotice($nurse)->body)->toContain('1 h 00 min before the end of Morning');

    // HR sees, per day, the shift it was measured against.
    shiftAs($hr);
    $rows = collect($this->getJson('/hr/attendance?per_page=50')->assertOk()->json('data'));
    expect($rows->firstWhere('employee.id', $nurse->id)['shift']['name'])->toBe('Morning')
        ->and($rows->firstWhere('employee.id', $doctor->id)['shift']['type'])->toBe('flexible');
});

it('lets HR export the shifts list like every other HR list; staff cannot', function () {
    shiftAs(shiftStaff());
    $this->postJson('/hr/shifts/export', ['format' => 'csv'])->assertNotFound(); // cannot even read the list

    shiftAs(shiftStaff(RoleName::HrManager, UserType::Hr));
    $this->postJson('/hr/shifts', ['name' => 'Morning', 'type' => 'fixed', 'start_time' => '07:00', 'end_time' => '15:00'])->assertCreated();
    $this->postJson('/hr/shifts/export', ['format' => 'csv'])->assertOk();
    $this->getJson('/meta/resources/shifts')->assertOk()->assertJsonPath('data.export_formats', ['xlsx', 'pdf', 'csv']);
});
