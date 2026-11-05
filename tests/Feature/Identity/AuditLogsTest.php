<?php

use App\Enums\Identity\RoleName;
use App\Models\Identity\AuditLog;
use App\Models\Identity\Person;
use App\Models\Identity\Role;
use App\Models\Identity\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('records model changes with only the changed and masked values', function () {
    $actor = actingWith('people.write');
    $person = Person::factory()->withNationalId('123456789')->create();
    $person->update(['first_name' => 'Changed', 'national_id' => '987654321']);
    $person->softDelete();
    $person->restore();

    $logs = AuditLog::query()->where('auditable_id', $person->id)->orderBy('id')->get();

    expect($logs->pluck('action.value')->all())->toBe(['created', 'updated', 'deleted', 'restored'])
        ->and($logs[0]->new_values['national_id'])->toBe('***')
        ->and($logs[1]->old_values)->toMatchArray(['national_id' => '***'])
        ->and($logs[1]->new_values)->toHaveKeys(['first_name', 'national_id'])
        ->and($logs[1]->new_values)->not->toHaveKey('version')
        ->and($logs[1]->user_id)->toBe($actor->id)
        ->and($logs[1]->module)->toBe('identity');
});

it('lists audit entries for a required date range with cursor pagination', function () {
    $admin = User::factory()->withRole(RoleName::SuperAdmin->value)->withTwoFactor()->create();
    Sanctum::actingAs($admin);
    Person::factory()->count(3)->create();

    $this->getJson('/audit-logs')->assertStatus(422);
    $this->getJson('/audit-logs?from=2026-01-01&to=2026-12-31')->assertStatus(422)->assertJsonValidationErrors('date_to', 'errors');

    $today = now('Asia/Bahrain')->toDateString();
    $page = $this->getJson("/audit-logs?date_from={$today}&date_to={$today}&action=created&per_page=2")->assertOk();
    expect($page->json('data'))->toHaveCount(2)->and($page->json('meta.next_cursor'))->toBeString();
    $this->getJson('/audit-logs?date_range=today&cursor='.$page->json('meta.next_cursor').'&per_page=2&action=created')->assertOk();
});

it('shows the history of one record through a whitelisted alias', function () {
    $admin = User::factory()->withRole(RoleName::SuperAdmin->value)->withTwoFactor()->create();
    Sanctum::actingAs($admin);
    $role = Role::create(['name' => 'tmp', 'display_name' => 'Tmp', 'guard_name' => 'sanctum']);
    $role->update(['display_name' => 'Temporary']);

    $this->getJson("/audit-logs/role/{$role->id}")->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.action', 'updated')->assertJsonPath('data.0.auditable_type', 'role')
        ->assertJsonPath('data.0.user.id', $admin->id);
    $this->getJson("/audit-logs/invoice_x/{$role->id}")->assertNotFound();
});

it('limits read_own to the entries of the signed-in user', function () {
    actingWith('people.write');
    Person::factory()->create();
    $me = actingWith('audit.read_own');
    Person::factory()->create();

    $rows = $this->getJson('/audit-logs?date_range=today')->assertOk()->json('data');
    expect(collect($rows)->pluck('user.id')->unique()->all())->toBe([$me->id]);
});
