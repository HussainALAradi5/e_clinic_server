<?php

use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Models\Identity\Role;
use App\Models\Identity\User;
use App\Notifications\Identity\ResetPasswordNotification;
use App\Utilities\Transfer\Xlsx\XlsxWriter;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');
    $this->seed([CountrySeeder::class, RolesAndPermissionsSeeder::class]);
});

function signInAs(RoleName $role): User
{
    $user = User::factory()->withRole($role->value)->withTwoFactor()->create();
    Sanctum::actingAs($user);

    return $user;
}

/** @param  list<list<string|null>>  $rows */
function sheet(array $rows): array
{
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    $xlsx = new XlsxWriter;
    $data = $xlsx->addSheet('Data');
    foreach ($rows as $row) {
        $data->row($row);
    }
    $xlsx->save($path);

    return ['file' => new UploadedFile($path, 'upload.xlsx', null, null, true)];
}

it('imports staff accounts, invited once the whole file is saved', function () {
    Notification::fake();
    signInAs(RoleName::Admin);
    $header = ['First name', 'Last name', 'Gender', 'Date of birth', 'Mobile', 'Email', 'Account type', 'Roles'];

    $this->post('/api/v1/users/import', sheet([
        $header,
        ['Sara', 'Ali', 'Female', '1990-01-02', '+97336001111', 'sara@example.com', 'Doctor', 'doctor, Cashier'],
        ['Omar', 'Saleh', 'Male', '1991-03-04', '+97336002222', 'omar@example.com', 'Patient', 'Patient'],
    ]), ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('meta.rows.0.row', 3)
        ->assertJsonPath('meta.rows.0.column', 'Account type');
    Notification::assertNothingSent();

    $this->post('/api/v1/users/import', sheet([
        $header,
        ['Sara', 'Ali', 'Female', '1990-01-02', '+97336001111', 'sara@example.com', 'Doctor', 'doctor, Cashier'],
        ['Omar', 'Saleh', 'Male', '1991-03-04', '+97336002222', 'omar@example.com', 'Receptionist', 'Nurse, unknown'],
    ]), ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('meta.rows.0.column', 'Roles');
    expect(User::query()->where('user_type', UserType::Doctor)->count())->toBe(0); // all or nothing
    Notification::assertNothingSent();

    $this->post('/api/v1/users/import', sheet([
        $header,
        ['Sara', 'Ali', 'Female', '1990-01-02', '+97336001111', 'sara@example.com', 'Doctor', 'doctor, Cashier'],
    ]), ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.created', 1);

    $sara = User::query()->where('user_type', UserType::Doctor)->sole();
    expect($sara->getRoleNames()->sort()->values()->all())->toBe(['cashier', 'doctor']);
    Notification::assertSentTo($sara, ResetPasswordNotification::class);
});

it('imports custom roles with the rights of the role they are based on', function () {
    signInAs(RoleName::Admin);

    $this->post('/api/v1/roles/import', sheet([
        ['Key', 'Display name', 'Based on'],
        ['senior_nurse', 'Senior nurse', 'Nurse'],
        ['night_desk', 'Night desk', null],
    ]), ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.created', 2);

    $nurse = Role::findByName(RoleName::Nurse->value, 'sanctum');
    $senior = Role::findByName('senior_nurse', 'sanctum');
    expect($senior->permissions->pluck('name')->sort()->values()->all())->toBe($nurse->permissions->pluck('name')->sort()->values()->all())
        ->and($senior->is_system)->toBeFalse()
        ->and(Role::findByName('night_desk', 'sanctum')->permissions)->toHaveCount(0);

    $this->post('/api/v1/roles/import', sheet([['Key', 'Display name'], ['dup', 'A'], ['dup', 'B']]), ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonPath('meta.rows.0.row', 3);
});

it('offers import on users and roles only to the roles that create them', function () {
    signInAs(RoleName::Admin);
    $this->getJson('/api/v1/meta/resources/users')->assertJsonPath('data.importable', true);
    $this->getJson('/api/v1/meta/resources/roles')->assertJsonPath('data.importable', true);

    signInAs(RoleName::HrManager);
    $this->getJson('/api/v1/meta/resources/users')->assertJsonPath('data.importable', true);
    $this->get('/api/v1/roles/import/template', ['Accept' => 'application/json'])->assertNotFound(); // roles are admin-only

    signInAs(RoleName::Receptionist);
    $this->get('/api/v1/users/import/template', ['Accept' => 'application/json'])->assertNotFound(); // cannot even read users
});
