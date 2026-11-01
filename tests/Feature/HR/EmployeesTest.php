<?php

use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserStatus;
use App\Modules\HR\Departments\Models\Department;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\Identity\Auth\Notifications\ResetPasswordNotification;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Identity\Users\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed([CountrySeeder::class, RolesAndPermissionsSeeder::class]);
    Notification::fake();
});

function hrAdmin(RoleName $role = RoleName::Admin): User
{
    $user = User::factory()->withRole($role->value)->withTwoFactor()->create();
    Sanctum::actingAs($user);

    return $user;
}

function department(string $code = 'NURS', ?string $parent = null): Department
{
    return Department::query()->create(['code' => $code, 'name' => ucfirst(strtolower($code)), 'parent_id' => $parent]);
}

/** @return array<string, mixed> */
function hirePayload(string $departmentId, array $overrides = []): array
{
    return array_replace_recursive([
        'person' => ['first_name' => 'Huda', 'last_name' => 'Karim', 'gender' => 'female', 'date_of_birth' => '1992-03-04', 'national_id' => '920304111', 'phone' => '+97336112233', 'email' => 'huda@example.com'],
        'department_id' => $departmentId,
        'job_title' => 'Staff nurse',
        'employment_type' => 'full_time',
        'hire_date' => '2026-02-01',
        'work_start_time' => '07:00',
        'work_end_time' => '15:00',
        'weekly_off_days' => [5, 6],
        'basic_salary' => '650.000',
        'iban' => 'BH67 BMAG 0000 1299 1234 56',
        'user_type' => 'medical_staff',
        'roles' => ['nurse'],
    ], $overrides);
}

it('keeps the department tree free of cycles and refuses to delete departments that still have staff', function () {
    hrAdmin();
    $clinical = department('CLIN');
    $nursing = department('NURS', $clinical->id);

    $this->getJson('/api/v1/hr/departments/tree')->assertOk()->assertJsonPath('data.0.code', 'CLIN')->assertJsonPath('data.0.children.0.code', 'NURS');
    $this->putJson("/api/v1/hr/departments/{$clinical->id}", ['parent_id' => $nursing->id])->assertStatus(422)->assertJsonPath('error_code', 'DEPARTMENT_CYCLE');
    $this->postJson('/api/v1/hr/departments', ['code' => 'NURS', 'name' => 'Again'])->assertStatus(422);

    $this->postJson('/api/v1/hr/employees', hirePayload($nursing->id))->assertCreated();
    $this->deleteJson("/api/v1/hr/departments/{$clinical->id}")->assertStatus(409)->assertJsonPath('error_code', 'DEPARTMENT_HAS_EMPLOYEES');
});

it('hires a new person as employee + invited account with one shared id', function () {
    hrAdmin();
    $dept = department();

    $response = $this->postJson('/api/v1/hr/employees', hirePayload($dept->id))->assertCreated()
        ->assertJsonPath('data.full_name', 'Huda Karim')
        ->assertJsonPath('data.account.user_type', 'medical_staff')
        ->assertJsonPath('data.account.roles', ['nurse'])
        ->assertJsonPath('data.basic_salary.amount', '650.000')
        ->assertJsonPath('data.iban', '******************3456');
    $id = $response->json('data.id');

    expect(Person::query()->find($id))->not->toBeNull()->and(User::query()->find($id)->hasRole('nurse'))->toBeTrue();
    Notification::assertSentTo(User::query()->find($id), ResetPasswordNotification::class);

    $this->postJson('/api/v1/hr/employees', hirePayload($dept->id, ['person' => ['phone' => '+97336999999', 'email' => 'other@example.com']]))
        ->assertStatus(409)->assertJsonPath('error_code', 'ALREADY_EMPLOYEE');
    $this->getJson('/api/v1/hr/employees?search=karim')->assertOk()->assertJsonCount(1, 'data');
    // Every word counts, across first and last name.
    $this->getJson('/api/v1/hr/employees?search='.urlencode('huda  karim'))->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/hr/employees?search='.urlencode('huda nobody'))->assertOk()->assertJsonCount(0, 'data');
});

it('reuses an existing person and gives staff who never sign in an inactive account without email', function () {
    hrAdmin();
    $dept = department();
    $person = Person::factory()->create(['email' => null]);

    $this->postJson('/api/v1/hr/employees', [...hirePayload($dept->id), 'person' => null, 'person_id' => $person->id, 'can_login' => false, 'user_type' => 'cashier', 'roles' => ['cashier']])
        ->assertCreated()->assertJsonPath('data.id', $person->id)->assertJsonPath('data.account.status', 'inactive');
    Notification::assertNothingSent();
});

it('hides salary without view_salary and lets nobody change salary without manage_salary', function () {
    hrAdmin();
    $id = $this->postJson('/api/v1/hr/employees', hirePayload(department()->id))->json('data.id');

    $hr = User::factory()->withTwoFactor()->create();
    grantPermissions($hr, 'employees.read_all', 'employees.write');
    Sanctum::actingAs($hr);
    $this->getJson("/api/v1/hr/employees/{$id}")->assertOk()->assertJsonMissingPath('data.basic_salary');
    $this->putJson("/api/v1/hr/employees/{$id}", ['version' => 1, 'basic_salary' => '900'])->assertForbidden();
    $this->putJson("/api/v1/hr/employees/{$id}", ['version' => 1, 'job_title' => 'Senior nurse'])->assertOk()->assertJsonPath('data.job_title', 'Senior nurse');
    $this->putJson("/api/v1/hr/employees/{$id}", ['version' => 1, 'job_title' => 'Stale'])->assertStatus(409)->assertJsonPath('error_code', 'CONCURRENT_MODIFICATION');
});

it('suspends, reactivates and terminates — the account follows and terminated staff are read-only', function () {
    hrAdmin();
    $id = $this->postJson('/api/v1/hr/employees', hirePayload(department()->id))->json('data.id');
    User::query()->find($id)->forceFill(['password' => 'Secret-Pass-2026!'])->save();

    $this->patchJson("/api/v1/hr/employees/{$id}/status", ['status' => 'suspended', 'reason' => 'Investigation'])->assertOk()->assertJsonPath('data.account.status', 'inactive');
    $this->patchJson("/api/v1/hr/employees/{$id}/status", ['status' => 'active', 'reason' => 'Cleared'])->assertOk()->assertJsonPath('data.account.status', 'active');

    $this->postJson("/api/v1/hr/employees/{$id}/salary-components", ['type' => 'allowance', 'name' => 'Housing', 'amount' => '150', 'effective_from' => '2026-02-01'])
        ->assertCreated()->assertJsonPath('data.amount.amount', '150.000');

    $this->postJson("/api/v1/hr/employees/{$id}/terminate", ['termination_date' => '2026-01-01', 'reason' => 'resignation'])->assertStatus(422);
    $this->postJson("/api/v1/hr/employees/{$id}/terminate", ['termination_date' => '2026-09-30', 'reason' => 'resignation'])
        ->assertOk()->assertJsonPath('data.status', 'terminated')->assertJsonPath('data.account.status', 'inactive');
    expect(User::query()->find($id)->status)->toBe(UserStatus::Inactive);

    $version = Employee::query()->find($id)->version;
    $this->putJson("/api/v1/hr/employees/{$id}", ['version' => $version, 'job_title' => 'x'])->assertStatus(409)->assertJsonPath('error_code', 'EMPLOYEE_TERMINATED');
});
