<?php

use App\Enums\Identity\NotificationChannel;
use App\Enums\Identity\NotificationStatus;
use App\Modules\Identity\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Identity\Notifications\Models\DeviceToken;
use App\Modules\Identity\Notifications\Models\Notification;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Identity\Users\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

function notify(Person $person, array $channels = [NotificationChannel::InApp], ?CarbonImmutable $at = null)
{
    return app(NotificationServiceInterface::class)->send('task.completed', $person, null, ['task' => 'Audit Export'], $at, $channels);
}

it('delivers in-app notifications to the inbox in the person language', function () {
    $user = User::factory()->create();
    $user->person->forceFill(['language' => 'ar'])->save();
    Sanctum::actingAs($user);

    notify($user->person->refresh());

    $this->getJson('/api/v1/notifications/mine')
        ->assertOk()
        ->assertJsonPath('meta.unread_count', 1)
        ->assertJsonPath('data.0.status', 'delivered')
        ->assertJsonPath('data.0.locale', 'ar')
        ->assertJsonPath('data.0.body', 'اكتملت مهمتك "Audit Export" بنجاح.');

    $id = $this->getJson('/api/v1/notifications/mine')->json('data.0.id');
    $this->patchJson("/api/v1/notifications/{$id}/read")->assertOk()->assertJsonPath('data.read_at', fn ($v) => $v !== null);
    $this->getJson('/api/v1/notifications/mine?unread=1')->assertJsonCount(0, 'data')->assertJsonPath('meta.unread_count', 0);

    notify($user->person, at: now()->toImmutable()->addSecond());
    app(NotificationServiceInterface::class)->dispatchDue();
    $this->travel(2)->seconds();
    app(NotificationServiceInterface::class)->dispatchDue();
    $this->patchJson('/api/v1/notifications/mine/read-all')->assertOk()->assertJsonPath('data.updated', 1);
});

it('does not read other users notifications', function () {
    $other = User::factory()->create();
    notify($other->person);

    actingWith();
    $this->patchJson('/api/v1/notifications/'.Notification::query()->sole()->id.'/read')->assertNotFound();
});

it('sends SMS through the driver, honours opt-outs and de-duplicates', function () {
    $person = Person::factory()->create();
    $at = now()->toImmutable();

    $rows = notify($person, [NotificationChannel::Sms, NotificationChannel::InApp], $at);
    expect($rows)->toHaveCount(1) // no account → no in-app row
        ->and($rows->first()->refresh()->status)->toBe(NotificationStatus::Sent)
        ->and($rows->first()->destination)->toBe($person->phone);

    expect(notify($person, [NotificationChannel::Sms], $at)->first()->id)->toBe($rows->first()->id);

    $person->forceFill(['notification_opt_out' => ['sms']])->save();
    expect(notify($person, [NotificationChannel::Sms], $at->addHour()))->toHaveCount(0);
});

it('shows the delivery log with masked destinations, resends and cancels', function () {
    $person = Person::factory()->create();
    $sent = notify($person, [NotificationChannel::Sms])->first();
    $queued = notify($person, [NotificationChannel::Sms], now()->toImmutable()->addDay())->first();

    $viewer = actingWith('notifications.read_all', 'notifications.write');
    $this->getJson("/api/v1/notifications?filter[person_id][eq]={$person->id}")
        ->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.destination', fn (string $d) => str_starts_with($d, '*') && str_ends_with($d, substr($person->phone, -4)));

    $this->postJson("/api/v1/notifications/{$sent->id}/resend")->assertStatus(409)->assertJsonPath('error_code', 'NOTIFICATION_NOT_RESENDABLE');
    $this->patchJson("/api/v1/notifications/{$queued->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
    $this->patchJson("/api/v1/notifications/{$queued->id}/cancel")->assertStatus(409);
    $this->postJson("/api/v1/notifications/{$queued->id}/resend")->assertCreated()->assertJsonPath('data.status', 'sent');

    $this->postJson('/api/v1/notifications/bulk', ['action' => 'cancel', 'ids' => [$sent->id]])
        ->assertOk()->assertJsonPath('data.failed.0.code', 'NOTIFICATION_NOT_CANCELLABLE');
    expect($viewer)->toBeInstanceOf(User::class);
});

it('accepts signed delivery webhooks only', function () {
    config(['notifications.webhooks.log.secret' => 'shh']);
    $sent = notify(Person::factory()->create(), [NotificationChannel::Sms])->first()->refresh();
    $body = json_encode(['message_id' => $sent->provider_message_id, 'status' => 'delivered']);

    $this->call('POST', '/api/v1/webhooks/notifications/log', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_SIGNATURE' => 'bad'], $body)
        ->assertStatus(401)->assertJsonPath('error_code', 'INVALID_WEBHOOK_SIGNATURE');

    $this->call('POST', '/api/v1/webhooks/notifications/log', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_SIGNATURE' => hash_hmac('sha256', (string) $body, 'shh')], $body)
        ->assertOk();
    expect($sent->refresh()->status)->toBe(NotificationStatus::Delivered);
});

it('registers push devices and forgets them on sign-out of all devices', function () {
    $user = actingWith();

    $this->postJson('/api/v1/notifications/devices', ['token' => 'fcm-1', 'app' => 'staff', 'platform' => 'android', 'device_name' => 'Pixel 8'])->assertCreated();
    $this->postJson('/api/v1/notifications/devices', ['token' => 'fcm-2', 'app' => 'staff', 'platform' => 'ios'])->assertCreated();
    $this->deleteJson('/api/v1/notifications/devices/fcm-2')->assertNoContent();

    expect(notify($user->person, [NotificationChannel::Push])->first()?->destination)->toBe('staff');

    $user->createToken('web');
    $this->postJson('/api/v1/auth/logout-all')->assertOk();
    expect(DeviceToken::query()->count())->toBe(0);
});
