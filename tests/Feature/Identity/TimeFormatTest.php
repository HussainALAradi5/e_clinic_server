<?php

it('reads times on the 12-hour clock by default and saves the person\'s own choice', function () {
    actingWith();

    $this->getJson('/auth/me')->assertOk()->assertJsonPath('data.user.time_format', '12h');
    $this->putJson('/auth/time-format', ['time_format' => '24h'])->assertOk()->assertJsonPath('data.time_format', '24h');
    $this->getJson('/auth/me')->assertJsonPath('data.user.time_format', '24h');

    $this->putJson('/auth/time-format', ['time_format' => 'noon'])->assertUnprocessable();
});
