<?php

use App\Models\Identity\User;
use Illuminate\Support\Facades\Cache;

// The wipe itself (migrate:fresh) cannot run inside a test's transaction; it is checked by hand on a scratch database.

it('does nothing again for a token it has already used', function () {
    Cache::forever('demo-reset-token', '2026-11-05');
    User::factory()->create();

    $this->artisan('clinic:reset-demo', ['token' => '2026-11-05'])->expectsOutput('Demo reset 2026-11-05 already done.')->assertExitCode(1);
    expect(User::query()->count())->toBe(1);
});

it('ignores an empty or "false" token, and refuses a production database without the demo', function () {
    User::factory()->create();
    $this->artisan('clinic:reset-demo', ['token' => 'false'])->assertExitCode(1);

    config(['app.seed_demo' => false]);
    app()->detectEnvironment(fn (): string => 'production');
    $this->artisan('clinic:reset-demo', ['token' => 'x'])->assertExitCode(1);
    expect(User::query()->count())->toBe(1);
});
