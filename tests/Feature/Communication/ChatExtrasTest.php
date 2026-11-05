<?php

use App\Enums\Identity\PresenceStatus;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Events\Communication\MessageReacted;
use App\Events\Communication\MessageSent;
use App\Events\Communication\PresenceChanged;
use App\Models\Communication\Message;
use App\Models\Identity\Notification;
use App\Models\Identity\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake(config('filesystems.default'));
    Event::fake([MessageSent::class, MessageReacted::class, PresenceChanged::class]);
});

function extrasUser(RoleName $role = RoleName::Doctor, UserType $type = UserType::Doctor): User
{
    return User::factory()->state(['user_type' => $type])->withRole($role->value)->create();
}

/** A group led by $leader with the other users as members; returns its id. */
function extrasGroup(object $test, User $leader, User ...$members): string
{
    Sanctum::actingAs($leader);

    return $test->postJson('/chat/conversations', ['type' => 'group', 'title' => 'Ward 3', 'member_ids' => array_map(fn (User $u) => $u->id, $members)])
        ->assertCreated()->json('data.id');
}

it('sends files and voice notes as attachments of the message, with the leader marked in a group', function () {
    $omar = extrasUser();
    $sara = extrasUser();
    $group = extrasGroup($this, $omar, $sara);

    $this->post("/chat/conversations/{$group}/messages", [
        'body' => 'Scan from this morning',
        'files' => [UploadedFile::fake()->image('xray.png', 200, 200)],
    ], ['Accept' => 'application/json'])->assertCreated()
        ->assertJsonPath('data.kind', 'media')
        ->assertJsonPath('data.sender.role', 'admin')
        ->assertJsonPath('data.attachments.0.name', 'xray.png')
        ->assertJsonPath('data.attachments.0.mime_type', 'image/png');

    Sanctum::actingAs($sara);
    $voice = UploadedFile::fake()->createWithContent('note.webm', file_get_contents(base_path('tests/Fixtures/voice.webm')));
    $this->post("/chat/conversations/{$group}/messages", ['files' => [$voice], 'voice' => '1'], ['Accept' => 'application/json'])
        ->assertCreated()->assertJsonPath('data.kind', 'voice')->assertJsonPath('data.body', null)->assertJsonPath('data.sender.role', 'member');

    // Neither text nor a file is refused; a file type outside the category is refused.
    $this->postJson("/chat/conversations/{$group}/messages", [])->assertUnprocessable();
    $this->post("/chat/conversations/{$group}/messages", ['files' => [UploadedFile::fake()->create('tool.exe', 10, 'application/x-msdownload')]], ['Accept' => 'application/json'])
        ->assertUnprocessable();
});

it('notifies mentioned members only, and group events, never plain messages', function () {
    $omar = extrasUser();
    $sara = extrasUser();
    $rana = extrasUser(RoleName::Receptionist, UserType::Receptionist);
    $outsider = extrasUser();
    $group = extrasGroup($this, $omar, $sara, $rana);

    // Sara and Rana were told they were added.
    expect(Notification::query()->where('template', 'chat.added')->count())->toBe(2);

    $this->postJson("/chat/conversations/{$group}/messages", ['body' => '@Sara please check bed 4', 'mentions' => [$sara->id, $outsider->id, $omar->id]])
        ->assertCreated()->assertJsonPath('data.mentions', [$sara->id]);
    $this->postJson("/chat/conversations/{$group}/messages", ['body' => 'Thanks all'])->assertCreated();

    $mentions = Notification::query()->where('template', 'chat.mention')->get();
    expect($mentions)->toHaveCount(1)->and($mentions->first()->person_id)->toBe($sara->id);

    $this->patchJson("/chat/conversations/{$group}/members/{$rana->id}", ['role' => 'admin'])->assertOk();
    expect(Notification::query()->where('template', 'chat.role')->where('person_id', $rana->id)->exists())->toBeTrue();
});

it('lets members react once each, replacing or taking back the emoji, and refuses others', function () {
    $omar = extrasUser();
    $sara = extrasUser();
    $outsider = extrasUser();
    $group = extrasGroup($this, $omar, $sara);
    $message = $this->postJson("/chat/conversations/{$group}/messages", ['body' => 'Rounds at 9'])->json('data.id');
    $url = "/chat/conversations/{$group}/messages/{$message}/reactions";

    $this->postJson($url, ['emoji' => '👍'])->assertOk()->assertJsonPath('data.reactions.0.emoji', '👍')->assertJsonPath('data.reactions.0.count', 1);
    Sanctum::actingAs($sara);
    $this->postJson($url, ['emoji' => '👍'])->assertOk()->assertJsonPath('data.reactions.0.count', 2);
    $this->postJson($url, ['emoji' => '❤️'])->assertOk()->assertJsonCount(2, 'data.reactions');
    $this->postJson($url, ['emoji' => '❤️'])->assertOk()->assertJsonCount(1, 'data.reactions')->assertJsonPath('data.reactions.0.user_ids', [$omar->id]);
    // Beyond the quick row, the "+" set; anything else is refused.
    $this->postJson($url, ['emoji' => '🩺'])->assertOk()->assertJsonPath('data.reactions.1.emoji', '🩺');
    $this->postJson($url, ['emoji' => '🩺'])->assertOk()->assertJsonCount(1, 'data.reactions');
    $this->postJson($url, ['emoji' => '🍕'])->assertUnprocessable();
    Event::assertDispatched(MessageReacted::class);

    Sanctum::actingAs($outsider);
    $this->postJson($url, ['emoji' => '👍'])->assertForbidden();
});

it('never lets a message of one chat be reached through another', function () {
    $omar = extrasUser();
    $sara = extrasUser();
    $rana = extrasUser();
    $first = extrasGroup($this, $omar, $sara);
    $second = extrasGroup($this, $omar, $rana);
    $secret = $this->postJson("/chat/conversations/{$first}/messages", ['body' => 'Only for Sara'])->json('data.id');
    $this->postJson("/chat/conversations/{$second}/messages", ['body' => 'Only for Rana'])->assertCreated();

    // Reacting to chat one's message through chat two's address finds nothing.
    $this->postJson("/chat/conversations/{$second}/messages/{$secret}/reactions", ['emoji' => '👍'])->assertNotFound();
    $this->getJson("/chat/conversations/{$second}/messages")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.body', 'Only for Rana');
    // Nor can a reply quote it from the other chat.
    $this->postJson("/chat/conversations/{$second}/messages", ['body' => 'Quoting', 'reply_to_id' => $secret])->assertNotFound();

    Sanctum::actingAs($rana);
    $this->getJson("/chat/conversations/{$first}/messages")->assertForbidden();
    expect(collect($this->getJson('/chat/conversations')->json('data'))->pluck('id')->all())->toBe([$second]);
});

it('filters the chat list on the server by type, read state and date, with counts', function () {
    $omar = extrasUser();
    $sara = extrasUser();
    $rana = extrasUser();
    $group = extrasGroup($this, $sara, $omar, $rana);
    $this->postJson("/chat/conversations/{$group}/messages", ['body' => 'Morning team'])->assertCreated();

    Sanctum::actingAs($omar);
    $direct = $this->postJson('/chat/conversations', ['type' => 'direct', 'user_id' => $rana->id])->json('data.id');
    $this->postJson("/chat/conversations/{$direct}/messages", ['body' => 'Coffee?'])->assertCreated();

    $ids = fn (string $query): array => collect($this->getJson('/chat/conversations'.$query)->assertOk()->json('data'))->pluck('id')->all();
    expect($ids(''))->toEqualCanonicalizing([$group, $direct])
        ->and($ids('?type=group'))->toBe([$group])
        ->and($ids('?type=direct'))->toBe([$direct])
        ->and($ids('?read=unread'))->toBe([$group])
        ->and($ids('?read=read'))->toBe([$direct])
        ->and($ids('?from=2000-01-01&to=2000-01-31'))->toBe([])
        // A full name in any case finds the one-to-one chat; a name not in the chat finds nothing.
        ->and($ids('?search='.urlencode(mb_strtolower($rana->person->full_name))))->toEqualCanonicalizing([$group, $direct])
        ->and($ids('?search='.urlencode(mb_strtolower($sara->person->full_name))))->toBe([$group])
        ->and($ids('?search=zzqx'))->toBe([]);
    $this->getJson('/chat/conversations?type=group')->assertJsonPath('data.0.messages_count', 1)->assertJsonPath('data.0.unread_count', 1);
    $this->getJson('/chat/conversations?read=maybe')->assertUnprocessable();
});

it('keeps each user their own chat settings, with defaults until saved', function () {
    $omar = extrasUser();
    Sanctum::actingAs($omar);

    $this->getJson('/chat/settings')->assertOk()
        ->assertJsonPath('data.my_color', 'blue')->assertJsonPath('data.others_color', 'green')
        ->assertJsonPath('data.count_in_badge', true)->assertJsonPath('data.reactions.0', '👍')->assertJsonPath('data.all_reactions.6', '😀');
    $this->putJson('/chat/settings', ['bubble_style' => 'square', 'my_color' => 'purple', 'count_in_badge' => false])->assertOk()
        ->assertJsonPath('data.bubble_style', 'square')->assertJsonPath('data.my_color', 'purple')->assertJsonPath('data.count_in_badge', false);
    $this->putJson('/chat/settings', ['my_color' => 'gold'])->assertUnprocessable();

    Sanctum::actingAs(extrasUser());
    $this->getJson('/chat/settings')->assertJsonPath('data.my_color', 'blue');
});

it('lets a user set and remove their own profile photo, shown as a signed link', function () {
    $omar = extrasUser();
    Sanctum::actingAs($omar);

    $url = $this->post('/auth/photo', ['photo' => UploadedFile::fake()->image('me.jpg', 300, 300)], ['Accept' => 'application/json'])
        ->assertOk()->json('data.avatar_url');
    expect($url)->toContain('signature=');
    $this->getJson('/auth/me')->assertJsonPath('data.user.avatar_url', fn (?string $v) => $v !== null);
    $this->post('/auth/photo', ['photo' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertUnprocessable();
    $this->deleteJson('/auth/photo')->assertNoContent();
    $this->getJson('/auth/me')->assertJsonPath('data.user.avatar_url', null);
});

it('replies to a message of the same chat with a quote of it, which outlives the quoted message', function () {
    $omar = extrasUser();
    $sara = extrasUser();
    $group = extrasGroup($this, $omar, $sara);
    $asked = $this->postJson("/chat/conversations/{$group}/messages", ['body' => 'Can someone check bed 4?'])->json('data.id');

    Sanctum::actingAs($sara);
    $reply = $this->postJson("/chat/conversations/{$group}/messages", ['body' => 'On it.', 'reply_to_id' => $asked])
        ->assertCreated()
        ->assertJsonPath('data.reply_to.id', $asked)
        ->assertJsonPath('data.reply_to.sender_id', $omar->id)
        ->assertJsonPath('data.reply_to.excerpt', 'Can someone check bed 4?')
        ->json('data.id');
    $this->getJson("/chat/conversations/{$group}/messages")->assertOk()->assertJsonPath('data.0.reply_to.id', $asked);
    $this->postJson("/chat/conversations/{$group}/messages", ['body' => 'x', 'reply_to_id' => 'not-a-uuid'])->assertUnprocessable();

    // The quoted message goes: the reply stays, without its quote.
    Message::query()->whereKey($asked)->delete();
    $this->getJson("/chat/conversations/{$group}/messages")->assertOk()
        ->assertJsonPath('data.0.id', $reply)->assertJsonPath('data.0.reply_to', null);
});

it('deletes a chat from my list only: a group after leaving it, the others keep it, nothing is erased', function () {
    $omar = extrasUser();
    $sara = extrasUser();
    $group = extrasGroup($this, $omar, $sara);
    $this->postJson("/chat/conversations/{$group}/messages", ['body' => 'Handover at 7'])->assertCreated();

    // Still in the group: leave first.
    Sanctum::actingAs($sara);
    $this->deleteJson("/chat/conversations/{$group}")->assertStatus(409)->assertJsonPath('error_code', 'CHAT_LEAVE_FIRST');

    // Left: the group stays on my list, read-only, showing what was said up to then.
    $this->deleteJson("/chat/conversations/{$group}/members/{$sara->id}")->assertNoContent();
    $this->travel(1)->minutes();
    Sanctum::actingAs($omar);
    $this->postJson("/chat/conversations/{$group}/messages", ['body' => 'After Sara left'])->assertCreated();
    Sanctum::actingAs($sara);
    $this->getJson('/chat/conversations')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.left', true)->assertJsonPath('data.0.unread_count', 1)
        ->assertJsonPath('data.0.last_message', null);
    $this->getJson("/chat/conversations/{$group}/messages")->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'Handover at 7');
    $this->postJson("/chat/conversations/{$group}/messages", ['body' => 'Hello?'])->assertStatus(409)->assertJsonPath('error_code', 'CHAT_LEFT');

    // Then delete it: gone from my list only.
    $this->deleteJson("/chat/conversations/{$group}")->assertNoContent();
    $this->getJson('/chat/conversations')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/chat/conversations/{$group}/messages")->assertForbidden();
    Sanctum::actingAs($omar);
    $this->getJson('/chat/conversations')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.left', false);
    expect(DB::table('messages')->where('conversation_id', $group)->count())->toBe(2)
        ->and(DB::table('conversation_participants')->where(['conversation_id' => $group, 'user_id' => $sara->id])->value('hidden_at'))->not->toBeNull();

    // One-to-one: off my list at once; when the other person writes it comes back, without the old messages.
    $direct = $this->postJson('/chat/conversations', ['type' => 'direct', 'user_id' => $sara->id])->assertCreated()->json('data.id');
    $this->postJson("/chat/conversations/{$direct}/messages", ['body' => 'Old news'])->assertCreated();
    Sanctum::actingAs($sara);
    $this->deleteJson("/chat/conversations/{$direct}")->assertNoContent();
    $this->travel(1)->minutes();
    $this->getJson('/chat/conversations')->assertOk()->assertJsonCount(0, 'data');
    Sanctum::actingAs($omar);
    $this->getJson('/chat/conversations?type=direct')->assertOk()->assertJsonCount(1, 'data');
    $this->postJson("/chat/conversations/{$direct}/messages", ['body' => 'Are you free?'])->assertCreated();
    Sanctum::actingAs($sara);
    $this->getJson('/chat/conversations')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $direct);
    $this->getJson("/chat/conversations/{$direct}/messages")->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'Are you free?');
});

it('lets admins delete a chat for everyone, list, read and restore it; nobody else', function () {
    $omar = extrasUser();
    $sara = extrasUser();
    $admin = User::factory()->state(['user_type' => UserType::Admin])->withRole(RoleName::Admin->value)->withTwoFactor()->create();
    $group = extrasGroup($this, $omar, $sara);
    $this->postJson("/chat/conversations/{$group}/messages", ['body' => 'Handover at 7'])->assertCreated();
    $this->deleteJson("/chat/conversations/{$group}/everyone")->assertForbidden();

    Sanctum::actingAs($admin);
    $this->deleteJson("/chat/conversations/{$group}/everyone")->assertNoContent();
    Sanctum::actingAs($sara);
    $this->getJson('/chat/conversations')->assertOk()->assertJsonCount(0, 'data');
    $this->postJson("/chat/conversations/{$group}/messages", ['body' => 'Still there?'])->assertStatus(409)->assertJsonPath('error_code', 'CHAT_DELETED');
    $this->getJson('/chat/conversations?deleted=1')->assertForbidden();

    Sanctum::actingAs($admin);
    $this->getJson('/chat/conversations?deleted=1')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $group)->assertJsonPath('data.0.deleted_by', $admin->person->full_name);
    $this->getJson("/chat/conversations/{$group}/messages")->assertOk()->assertJsonPath('data.0.body', 'Handover at 7');
    $this->patchJson("/chat/conversations/{$group}/restore")->assertOk()->assertJsonPath('data.deleted_at', null);
    Sanctum::actingAs($sara);
    $this->getJson('/chat/conversations')->assertOk()->assertJsonCount(1, 'data');
    $this->patchJson("/chat/conversations/{$group}/restore")->assertForbidden();
});

it('makes me Online when I sign in and Offline when I sign out everywhere, and lets me switch it by hand', function () {
    $omar = extrasUser();
    $omar->person->forceFill(['email' => 'omar.presence@demo.test'])->save();
    $omar->forceFill(['password' => Hash::make('Presence-Pass-2026!')])->save();
    $sara = extrasUser();
    expect($omar->refresh()->presence->value)->toBe('offline');

    $phone = $this->postJson('/auth/login', ['email' => 'omar.presence@demo.test', 'password' => 'Presence-Pass-2026!', 'device_name' => 'phone'])
        ->assertOk()->json('data.token');
    $web = $this->postJson('/auth/login', ['email' => 'omar.presence@demo.test', 'password' => 'Presence-Pass-2026!', 'device_name' => 'web'])
        ->assertOk()->json('data.token');
    expect($omar->refresh()->presence->value)->toBe('online');

    // Others see it in a one-to-one chat and on the member.
    Sanctum::actingAs($sara);
    $direct = $this->postJson('/chat/conversations', ['type' => 'direct', 'user_id' => $omar->id])->assertCreated()
        ->assertJsonPath('data.partner_presence', 'online')->json('data.id');

    // By hand.
    $this->app['auth']->forgetGuards();
    $this->withToken($web)->putJson('/auth/presence', ['status' => 'offline'])->assertOk()->assertJsonPath('data.presence', 'offline');
    $this->withToken($web)->putJson('/auth/presence', ['status' => 'away'])->assertStatus(422);
    $this->withToken($web)->getJson('/auth/me')->assertOk()->assertJsonPath('data.user.presence', 'offline');
    $this->withToken($web)->putJson('/auth/presence', ['status' => 'online'])->assertOk();

    // Signing out on one device keeps me online on the other; the last one takes me offline.
    $this->withToken($web)->postJson('/auth/logout')->assertNoContent();
    expect($omar->refresh()->presence->value)->toBe('online');
    $this->app['auth']->forgetGuards();
    $this->withToken($phone)->postJson('/auth/logout')->assertNoContent();
    expect($omar->refresh()->presence->value)->toBe('offline');

    Sanctum::actingAs($sara);
    $this->getJson("/chat/conversations/{$direct}")->assertOk()->assertJsonPath('data.partner_presence', 'offline');

    // Every change went out live to the staff presence channel (sign-in, off, on, last sign-out).
    Event::assertDispatchedTimes(PresenceChanged::class, 4);
    Event::assertDispatched(PresenceChanged::class, fn (PresenceChanged $e): bool => $e->userId === $omar->id
        && $e->presence === PresenceStatus::Offline && $e->broadcastOn()[0]->name === 'private-staff.presence');
});

it('still sends, reacts and reads when the real-time server is down', function () {
    // A closed port: every broadcast fails (as on a host without Reverb); the chat must not.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's', 'broadcasting.connections.reverb.app_id' => 'a',
        'broadcasting.connections.reverb.options' => ['host' => '127.0.0.1', 'port' => 1, 'scheme' => 'http', 'useTLS' => false],
    ]);
    // The real dispatcher back (beforeEach fakes the chat events), so the broadcasts really try to go out.
    Event::swap(Event::getFacadeRoot()->dispatcher);
    $omar = extrasUser();
    $sara = extrasUser();
    $group = extrasGroup($this, $omar, $sara);
    $sent = $this->postJson("/chat/conversations/{$group}/messages", ['body' => 'Anyone there?'])->assertCreated()->json('data.id');

    Sanctum::actingAs($sara);
    $this->postJson("/chat/conversations/{$group}/messages/{$sent}/reactions", ['emoji' => '👍'])->assertOk();
    $this->postJson("/chat/conversations/{$group}/read")->assertNoContent();
});
