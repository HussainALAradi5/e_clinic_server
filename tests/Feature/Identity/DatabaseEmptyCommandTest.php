<?php

use App\Models\Identity\User;

it('tells the start script to seed only while the database has no users', function () {
    $this->artisan('clinic:database-empty')->expectsOutput('users: 0')->assertExitCode(0);

    User::factory()->create();
    $this->artisan('clinic:database-empty')->expectsOutput('users: 1')->assertExitCode(1);
});
