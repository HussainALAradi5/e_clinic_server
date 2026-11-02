<?php

use App\Models\Identity\Permission;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit/Shared');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/** Grants permissions directly (creating them for the API guard when missing). */
function grantPermissions(User $user, string ...$permissions): User
{
    foreach ($permissions as $name) {
        Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'sanctum'], ['module' => explode('.', $name)[0]]);
    }
    $user->givePermissionTo($permissions);

    return $user;
}

/** A new signed-in user holding exactly these permissions. */
function actingWith(string ...$permissions): User
{
    $user = grantPermissions(User::factory()->create(), ...$permissions);
    Sanctum::actingAs($user);

    return $user;
}
