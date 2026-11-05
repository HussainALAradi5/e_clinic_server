<?php

use App\Models\Identity\User;
use Laravel\Sanctum\Sanctum;

function saveView(array $overrides = [])
{
    return test()->postJson('/views', [
        'resource' => 'users',
        'name' => 'Active staff',
        'query' => ['filter' => ['status' => ['eq' => 'active']], 'sort' => '-last_login_at'],
        'columns' => ['code', 'full_name', 'status'],
        ...$overrides,
    ]);
}

it('saves, renames and deletes a personal view', function () {
    actingWith('users.read_own');

    $id = saveView()->assertCreated()->assertJsonPath('data.resource', 'users')->assertJsonPath('data.is_mine', true)->assertJsonPath('data.columns.1', 'full_name')->json('data.id');
    saveView()->assertStatus(409)->assertJsonPath('error_code', 'SAVED_VIEW_NAME_TAKEN');

    $this->putJson("/views/{$id}", ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.name', 'Renamed');
    $this->getJson('/views?resource=users')->assertOk()->assertJsonCount(1, 'data');
    $this->deleteJson("/views/{$id}")->assertNoContent();
    $this->getJson('/views')->assertJsonCount(0, 'data');
});

it('validates the stored query against the resource list config', function () {
    actingWith('users.read_all');

    saveView(['query' => ['filter' => ['password' => ['eq' => 'x']]]])->assertUnprocessable();
    saveView(['resource' => 'unknown'])->assertNotFound();
});

it('keeps one default view per resource', function () {
    actingWith('users.read_all');
    $first = saveView(['is_default' => true])->json('data.id');
    $second = saveView(['name' => 'Locked'])->json('data.id');

    $this->patchJson("/views/{$second}/default")->assertOk()->assertJsonPath('data.is_default', true);
    $this->getJson('/views?filter[is_default][eq]=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second);
    expect($first)->not->toBe($second);
});

it('shares views only with read_all and only to users who can read the resource', function () {
    actingWith('users.read_own');
    saveView(['is_shared' => true])->assertUnprocessable();

    $owner = actingWith('users.read_all');
    $id = saveView(['is_shared' => true])->assertCreated()->json('data.id');

    Sanctum::actingAs(grantPermissions(User::factory()->create(), 'users.read_own'));
    $this->getJson('/views')->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.is_mine', false);
    $this->putJson("/views/{$id}", ['name' => 'Mine now'])->assertForbidden();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/views')->assertJsonCount(0, 'data');
    expect($owner)->toBeInstanceOf(User::class);
});

it('describes personal lists to every signed-in user', function () {
    actingWith();

    $this->getJson('/meta/resources/background_tasks')->assertOk()->assertJsonPath('data.resource', 'background_tasks');
    $this->getJson('/meta/resources/list_views')->assertOk();
    $this->getJson('/meta/resources/notifications')->assertNotFound();
});
