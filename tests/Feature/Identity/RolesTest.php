<?php

use App\Enums\Identity\RoleName;
use App\Modules\Identity\Roles\Models\Role;
use App\Modules\Identity\Users\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

function signedInSuperAdmin(): User
{
    $user = User::factory()->withRole(RoleName::SuperAdmin->value)->withTwoFactor()->create();
    Sanctum::actingAs($user);

    return $user;
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('seeds the system roles with the default access matrix', function () {
    $receptionist = Role::findByName('receptionist', 'sanctum');

    expect(Role::query()->where('is_system', true)->count())->toBe(count(RoleName::cases()))
        ->and($receptionist->code)->toStartWith('ROL-')
        ->and($receptionist->hasPermissionTo('appointments.read_all'))->toBeTrue()
        ->and($receptionist->hasPermissionTo('appointments.read_own'))->toBeTrue()
        ->and($receptionist->hasPermissionTo('invoices.issue'))->toBeTrue()
        ->and($receptionist->hasPermissionTo('invoices.delete'))->toBeFalse()
        ->and(Role::findByName('admin', 'sanctum')->hasPermissionTo('fiscal.reopen'))->toBeFalse()
        ->and(Role::findByName('super_admin', 'sanctum')->hasPermissionTo('fiscal.reopen'))->toBeTrue();
});

it('returns the effective access of the signed-in user', function () {
    $user = User::factory()->withRole(['doctor', 'nurse'])->create();
    Sanctum::actingAs($user);

    $access = $this->getJson('/api/v1/roles/me/access')->assertOk()->json('data');

    expect($access['roles'])->toEqualCanonicalizing(['doctor', 'nurse'])
        ->and($access['permissions'])->toContain('treatments.create', 'records.read_own', 'patients.view_medical', 'patients.view_history')
        ->not->toContain('records.read_all');
});

it('lists roles with counts, permissions grouped by module and protected resources', function () {
    signedInSuperAdmin();

    $this->getJson('/api/v1/roles?sort=display_name&per_page=20')->assertOk()->assertJsonCount(count(RoleName::cases()), 'data')
        ->assertJsonPath('data.0.users_count', 0);
    $groups = collect($this->getJson('/api/v1/roles/permissions')->assertOk()->json('data'));
    expect($groups->pluck('module'))->toContain('identity', 'payments');
    $this->getJson('/api/v1/roles/resources')->assertOk()->assertJsonFragment(['resource' => 'invoices', 'actions' => ['cancel', 'issue']]);
});

it('creates, updates, archives, deletes and restores custom roles', function () {
    signedInSuperAdmin();

    $id = $this->postJson('/api/v1/roles', ['name' => 'lab_tech', 'display_name' => 'Lab technician', 'permissions' => ['orders.read_all', 'orders.read_own', 'orders.perform']])
        ->assertCreated()->assertJsonPath('data.permissions_count', 3)->json('data.id');

    $this->putJson("/api/v1/roles/{$id}", ['display_name' => 'Laboratory', 'permissions' => ['orders.read_own']])
        ->assertOk()->assertJsonPath('data.display_name', 'Laboratory')->assertJsonPath('data.permissions', ['orders.read_own']);
    $this->patchJson("/api/v1/roles/{$id}/archive")->assertOk()->assertJsonPath('data.is_archived', true);
    $this->getJson('/api/v1/roles?filter[id][eq]='.$id)->assertJsonCount(0, 'data');
    $this->patchJson("/api/v1/roles/{$id}/unarchive")->assertOk()->assertJsonPath('data.is_archived', false);

    $this->deleteJson("/api/v1/roles/{$id}")->assertNoContent();
    $this->getJson("/api/v1/roles/{$id}")->assertNotFound();
    $this->patchJson("/api/v1/roles/{$id}/restore")->assertOk()->assertJsonPath('data.is_deleted', false);
});

it('protects system roles and roles in use', function () {
    signedInSuperAdmin();
    $doctor = Role::findByName('doctor', 'sanctum');
    $custom = Role::create(['name' => 'temp', 'display_name' => 'Temp', 'guard_name' => 'sanctum']);
    User::factory()->create()->assignRole($custom);

    $this->putJson("/api/v1/roles/{$doctor->id}", ['name' => 'physician'])->assertStatus(409)->assertJsonPath('error_code', 'ROLE_SYSTEM_PROTECTED');
    $this->putJson("/api/v1/roles/{$doctor->id}", ['permissions' => []])->assertStatus(409);
    $this->putJson("/api/v1/roles/{$doctor->id}", ['display_name' => 'Physician'])->assertOk();
    $this->deleteJson("/api/v1/roles/{$doctor->id}")->assertStatus(409)->assertJsonPath('error_code', 'ROLE_SYSTEM_PROTECTED');
    $this->deleteJson("/api/v1/roles/{$custom->id}")->assertStatus(409)->assertJsonPath('error_code', 'ROLE_IN_USE');
});

it('reads and saves the access matrix, normalising flags and revoking affected tokens', function () {
    signedInSuperAdmin();
    $cashier = Role::findByName('cashier', 'sanctum');
    $holder = User::factory()->withRole('cashier')->create();
    $holder->createToken('device');

    $row = collect($this->getJson("/api/v1/roles/{$cashier->id}/access")->assertOk()->json('data'))->firstWhere('resource', 'invoices');
    expect($row)->toMatchArray(['read_all' => true, 'write' => true, 'delete' => false, 'actions' => ['issue'], 'available_actions' => ['cancel', 'issue']]);

    $saved = collect($this->putJson("/api/v1/roles/{$cashier->id}/access", ['rows' => [
        ['resource' => 'invoices', 'read_all' => true, 'write' => true, 'delete' => true, 'actions' => ['issue', 'cancel']],
    ]])->assertOk()->json('data'))->firstWhere('resource', 'invoices');

    expect($saved)->toMatchArray(['read_own' => true, 'delete' => true, 'actions' => ['cancel', 'issue']])
        ->and($cashier->refresh()->hasPermissionTo('payments.create'))->toBeTrue()
        ->and($holder->tokens()->count())->toBe(0);

    $this->putJson("/api/v1/roles/{$cashier->id}/access", ['rows' => [['resource' => 'rooms', 'write' => true]]])->assertStatus(422);
    $this->putJson("/api/v1/roles/{$cashier->id}/access", ['rows' => [['resource' => 'rooms', 'read_all' => true, 'actions' => ['fly']]]])->assertStatus(422);
    $super = Role::findByName('super_admin', 'sanctum');
    $this->putJson("/api/v1/roles/{$super->id}/access", ['rows' => [['resource' => 'rooms']]])->assertStatus(409);
});

it('blocks privilege escalation', function () {
    $hr = User::factory()->withRole('hr_manager')->withTwoFactor()->create();
    grantPermissions($hr, 'roles.read_all', 'roles.create', 'roles.write', 'roles.assign');
    Sanctum::actingAs($hr);
    $target = User::factory()->create();

    $this->postJson('/api/v1/roles', ['name' => 'sneaky', 'display_name' => 'Sneaky', 'permissions' => ['journal.post']])
        ->assertForbidden()->assertJsonPath('error_code', 'ROLE_PRIVILEGE_ESCALATION')->assertJsonPath('meta.permissions', ['journal.post']);
    $this->putJson("/api/v1/roles/users/{$target->id}", ['roles' => ['accountant']])->assertForbidden()->assertJsonPath('error_code', 'ROLE_PRIVILEGE_ESCALATION');
    $this->putJson("/api/v1/roles/users/{$target->id}", ['roles' => ['super_admin']])->assertForbidden();
    // A role counts as held when the caller already has all of its permissions.
    $this->postJson('/api/v1/roles', ['name' => 'hr_clerk', 'display_name' => 'HR clerk', 'permissions' => ['leaves.read_all', 'leaves.read_own']])->assertCreated();
    $this->putJson("/api/v1/roles/users/{$target->id}", ['roles' => ['hr_clerk']])->assertOk()->assertJsonPath('data.roles.0.name', 'hr_clerk');
});

it('replaces user roles but never the own roles or the last super admin', function () {
    $admin = signedInSuperAdmin();
    $user = User::factory()->withRole('nurse')->create();
    $user->createToken('device');

    $this->putJson("/api/v1/roles/users/{$user->id}", ['roles' => ['doctor', 'nurse']])->assertOk()->assertJsonCount(2, 'data.roles');
    expect($user->tokens()->count())->toBe(0);

    $this->putJson("/api/v1/roles/users/{$admin->id}", ['roles' => ['admin']])->assertStatus(409)->assertJsonPath('error_code', 'USER_CANNOT_MODIFY_SELF');

    $second = User::factory()->withRole('super_admin')->withTwoFactor()->create();
    Sanctum::actingAs($second);
    $this->putJson("/api/v1/roles/users/{$admin->id}", ['roles' => ['admin']])->assertOk();
    Sanctum::actingAs(User::factory()->withRole('super_admin')->withTwoFactor()->create());
    $second->syncRoles(['super_admin']);
    $this->putJson("/api/v1/roles/users/{$second->id}", ['roles' => ['admin']])->assertOk();
});

it('adds people to a role and removes them, keeping their other roles', function () {
    $admin = signedInSuperAdmin();
    $nurse = User::factory()->withRole('nurse')->create();
    $doctor = User::factory()->withRole('doctor')->create();
    $role = Role::query()->where('name', 'doctor')->firstOrFail();

    // The picker only offers people who do not hold the role yet.
    $this->getJson('/api/v1/users?without_role=doctor&per_page=100')->assertOk()
        ->assertJsonMissing(['id' => $doctor->id])->assertJsonFragment(['id' => $nurse->id]);

    $this->postJson("/api/v1/roles/{$role->id}/members", ['user_ids' => [$nurse->id, $doctor->id]])
        ->assertOk()->assertJsonPath('data.added', 1);
    expect($nurse->fresh()->getRoleNames()->sort()->values()->all())->toBe(['doctor', 'nurse']);

    $this->deleteJson("/api/v1/roles/{$role->id}/members/{$nurse->id}")->assertOk()->assertJsonCount(1, 'data.roles');
    expect($nurse->fresh()->getRoleNames()->all())->toBe(['nurse']);

    // Nobody is left without a role, and nobody changes their own roles.
    $this->deleteJson("/api/v1/roles/{$role->id}/members/{$doctor->id}")->assertUnprocessable()->assertJsonValidationErrors('user');
    $this->postJson("/api/v1/roles/{$role->id}/members", ['user_ids' => [$admin->id]])->assertStatus(409)->assertJsonPath('error_code', 'USER_CANNOT_MODIFY_SELF');

    // An archived role takes no new members.
    $role->forceFill(['is_archived' => true])->save();
    $this->postJson("/api/v1/roles/{$role->id}/members", ['user_ids' => [$nurse->id]])->assertUnprocessable()->assertJsonValidationErrors('user_ids');
});

it('stops people from handing out a role they do not hold themselves', function () {
    $hr = User::factory()->withRole('hr_manager')->withTwoFactor()->create();
    grantPermissions($hr, 'roles.read_all', 'roles.write', 'roles.assign');
    Sanctum::actingAs($hr);
    $role = Role::query()->where('name', 'accountant')->firstOrFail();

    $this->postJson("/api/v1/roles/{$role->id}/members", ['user_ids' => [User::factory()->create()->id]])
        ->assertForbidden()->assertJsonPath('error_code', 'ROLE_PRIVILEGE_ESCALATION');
});

it('runs bulk actions on roles', function () {
    signedInSuperAdmin();
    $ids = collect(['one', 'two'])->map(fn (string $name) => Role::create(['name' => $name, 'display_name' => ucfirst($name), 'guard_name' => 'sanctum'])->id)->all();

    $this->postJson('/api/v1/roles/bulk', ['action' => 'archive', 'ids' => $ids])->assertOk()->assertJsonPath('data.succeeded', 2);
    $doctor = Role::findByName('doctor', 'sanctum');
    $this->postJson('/api/v1/roles/bulk', ['action' => 'delete', 'ids' => [$doctor->id]])->assertOk()
        ->assertJsonPath('data.failed.0.code', 'ROLE_SYSTEM_PROTECTED');
});
