<?php

use App\Enums\Identity\RoleName;
use App\Models\Identity\User;
use App\Models\Reference\ClinicProfile;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed([CountrySeeder::class, RolesAndPermissionsSeeder::class]);
});

it('shows admins the Tap addresses and saves the secret key encrypted, never sending it back', function () {
    Sanctum::actingAs(User::factory()->withRole(RoleName::Admin->value)->create());

    $this->getJson('/reference/clinic-profile/online-payments')->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.has_secret_key', false)
        ->assertJsonPath('data.webhook_url', route('webhooks.tap'))
        ->assertJsonPath('data.return_url', route('online-payments.return'));

    $this->putJson('/reference/clinic-profile/online-payments', ['enabled' => true, 'secret_key' => 'not-a-tap-key'])->assertStatus(422);
    $saved = $this->putJson('/reference/clinic-profile/online-payments', [
        'enabled' => true, 'base_url' => 'https://api.tap.company/v2', 'source' => 'src_card', 'secret_key' => 'sk_test_abcd1234wxyz',
    ])->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.driver', 'tap')
        ->assertJsonPath('data.source', 'src_card')
        ->assertJsonPath('data.has_secret_key', true)
        ->assertJsonPath('data.secret_key_hint', 'sk_test_…wxyz');
    expect($saved->getContent())->not->toContain('sk_test_abcd1234wxyz')
        ->and((string) DB::table('clinic_profiles')->value('tap_secret_key'))->not->toContain('sk_test_abcd1234wxyz')
        ->and(ClinicProfile::query()->first()?->tap_secret_key)->toBe('sk_test_abcd1234wxyz');
    $this->getJson('/public/clinic-profile')->assertOk()->assertJsonPath('data.online_payments_enabled', true)
        ->assertJsonMissingPath('data.tap_secret_key');

    // No key in the request keeps the saved one; clear_secret_key removes it.
    $this->putJson('/reference/clinic-profile/online-payments', ['enabled' => false])->assertOk()->assertJsonPath('data.has_secret_key', true);
    $this->putJson('/reference/clinic-profile/online-payments', ['enabled' => false, 'clear_secret_key' => true])->assertOk()
        ->assertJsonPath('data.has_secret_key', false)->assertJsonPath('data.driver', 'fake');
});

it('lets only admins see or change the online payment settings', function () {
    Sanctum::actingAs(User::factory()->withRole(RoleName::Accountant->value)->create());
    $this->getJson('/reference/clinic-profile/online-payments')->assertForbidden();
    $this->putJson('/reference/clinic-profile/online-payments', ['enabled' => true])->assertForbidden();
});
