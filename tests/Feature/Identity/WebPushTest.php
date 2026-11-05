<?php

use App\Enums\Identity\NotificationChannel;
use App\Interfaces\Identity\NotificationServiceInterface;
use App\Jobs\Identity\SendWebPush;
use App\Models\Identity\PushSubscription;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config(['notifications.webpush.public_key' => 'test-public-key']);
});

/** @return array<string, mixed> */
function browserSubscription(string $endpoint = 'https://push.example.test/send/abc'): array
{
    return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BPublicKeyOfTheBrowser', 'auth' => 'AuthSecret']];
}

it('turns phone and browser notifications off and on with the push opt-out', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/notifications/settings')->assertOk()
        ->assertJsonPath('data.push', true)->assertJsonPath('data.web_push_key', 'test-public-key');

    $this->putJson('/notifications/settings', ['push' => false])->assertOk()->assertJsonPath('data.push', false);
    expect($user->person->refresh()->notification_opt_out)->toBe(['push']);

    $this->putJson('/notifications/settings', ['push' => true])->assertOk()->assertJsonPath('data.push', true);
    expect($user->person->refresh()->notification_opt_out)->toBe([]);
    $this->putJson('/notifications/settings', [])->assertStatus(422);
});

it('keeps one subscription per browser and forgets it on unsubscribe; only https endpoints', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/notifications/web-push', browserSubscription())->assertCreated();
    $this->postJson('/notifications/web-push', browserSubscription())->assertCreated();
    expect(PushSubscription::query()->where('user_id', $user->id)->count())->toBe(1);

    $this->postJson('/notifications/web-push', browserSubscription('http://push.example.test/x'))->assertStatus(422);

    $this->deleteJson('/notifications/web-push', ['endpoint' => 'https://push.example.test/send/abc'])->assertNoContent();
    expect(PushSubscription::query()->count())->toBe(0);
});

it('pushes in-app messages to subscribed browsers only while notifications are on', function () {
    Queue::fake([SendWebPush::class]);
    $user = User::factory()->create();
    // A second apart: the same message at the same instant is one message.
    $send = function () use ($user) {
        $this->travel(1)->seconds();
        app(NotificationServiceInterface::class)->send('task.completed', $user->person->refresh(), null, ['task' => 'Export'], null, [NotificationChannel::InApp]);
    };

    // No browser yet: nothing to push.
    $send();
    Queue::assertNothingPushed();

    PushSubscription::query()->create(['user_id' => $user->id, 'endpoint' => 'https://push.example.test/1', 'public_key' => 'k', 'auth_token' => 'a']);
    $send();
    Queue::assertPushed(SendWebPush::class, 1);

    $user->person->forceFill(['notification_opt_out' => ['push']])->save();
    $send();
    Queue::assertPushed(SendWebPush::class, 1);
});
