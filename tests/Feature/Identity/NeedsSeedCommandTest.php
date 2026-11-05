<?php

use App\Models\Identity\User;
use Database\Seeders\DemoSeeder;

it('asks for seeding while there are no users, or no demo accounts while the demo is wanted', function () {
    $this->artisan('clinic:needs-seed')->expectsOutput('users: 0, demo accounts: 0')->assertExitCode(0);

    // Only the super admin (a database restored or started without the demo): still needs the demo.
    $admin = User::factory()->create();
    $admin->person->forceFill(['email' => 'admin@eclinic.local'])->save();
    $this->artisan('clinic:needs-seed')->expectsOutput('users: 1, demo accounts: 0')->assertExitCode(0);

    $demo = User::factory()->create();
    $demo->person->forceFill(['email' => 'reception'.DemoSeeder::EMAIL_DOMAIN])->save();
    $this->artisan('clinic:needs-seed')->expectsOutput('users: 2, demo accounts: 1')->assertExitCode(1);
});

it('leaves a production database without the demo alone once it has users', function () {
    config(['app.seed_demo' => false]);
    app()->detectEnvironment(fn (): string => 'production');
    User::factory()->create();

    $this->artisan('clinic:needs-seed')->assertExitCode(1);
});
