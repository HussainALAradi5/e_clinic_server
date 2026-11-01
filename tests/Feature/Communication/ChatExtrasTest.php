<?php

use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Modules\Communication\Chat\Events\MessageReacted;
use App\Modules\Communication\Chat\Events\MessageSent;
use App\Modules\Communication\Chat\Models\Message;
use App\Modules\Identity\Notifications\Models\Notification;
use App\Modules\Identity\Users\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake(config('filesystems.default'));
    Event::fake([MessageSent::class, MessageReacted::class]);
});

function extrasUser(RoleName $role = RoleName::Doctor, UserType $type = UserType::Doctor): User
{
    return User::factory()->state(['user_type' => $type])->withRole($role->value)->create();
}

/** A group led by $leader with the other users as members; returns its id. */
function extrasGroup(object $test, User $leader, User ...$members): string
{
    Sanctum::actingAs($leader);

    return $test->postJson('/api/v1/chat/conversations', ['type' => 'group', 'title' => 'Ward 3', 'member_ids' => array_map(fn (User $u) => $u->id, $members)])
        ->assertCreated()->json('data.id');
}

it('sends files and voice notes as attachments of the message, with the leader marked in a group', function () {
    $omar = extrasUser();
    $sara = extrasUser();
    $group = extrasGroup($this, $omar, $sara);

    $this->post("/api/v1/chat/conversations/{$group}/messages", [
        'body' => 'Scan from this morning',
        'files' => [UploadedFile::fake()->image('xray.png', 200, 200)],
    ], ['Accept' => 'application/json'])->assertCreated()
        ->assertJsonPath('data.kind', 'media')
        ->assertJsonPath('data.sender.role', 'admin')
        ->assertJsonPath('data.attachments.0.name', 'xray.png')
        ->assertJsonPath('data.attachments.0.mime_type', 'image/png');

    Sanctum::actingAs($sara);
    $voice = UploadedFile::fake()->createWithContent('note.webm', file_get_contents(base_path('tests/Fixtures/voice.webm')));
    $this->post("/api/v1/chat/conversations/{$group}/messages", ['files' => [$voice], 'voice' => '1'], ['Accept' => 'application/json'])
        ->assertCreated()->assertJsonPath('data.kind', 'voice')->assertJsonPath('data.body', null)->assertJsonPath('data.sender.role', 'member');

    // Neither text nor a file is refused; a file type outside the category is refused.
    $this->postJson("/api/v1/chat/conversations/{$group}/messages", [])->assertUnprocessable();
    $this->post("/api/v1/chat/conversations/{$group}/messages", ['files' => [UploadedFile::fake()->create('tool.exe', 10, 'application/x-msdownload')]], ['Accept' => 'application/json'])
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

    $this->postJson("/api/v1/chat/conversations/{$group}/messages", ['body' => '@Sara please check bed 4', 'mentions' => [$sara->id, $outsider->id, $omar->id]])
        ->assertCreated()->assertJsonPath('data.mentions', [$sara->id]);
    $this->postJson("/api/v1/chat/conversations/{$group}/messages", ['body' => 'Thanks all'])->assertCreated();

    $mentions = Notification::query()->where('template', 'chat.mention')->get();
    expect($mentions)->toHaveCount(1)->and($mentions->first()->person_id)->toBe($sara->id);

    $this->patchJson("/api/v1/chat/conversations/{$group}/members/{$rana->id}", ['role' => 'admin'])->assertOk();
    expect(Notification::query()->where('template', 'chat.role')->where('person_id', $rana->id)->exists())->toBeTrue();
});

it('lets members react once each, replacing or taking back the emoji, and refuses others', function () {
    $omar = extrasUser();
    $sara = extrasUser();
    $outsider = extrasUser();
    $group = extrasGroup($this, $omar, $sara);
    $message = $this->postJson("/api/v1/chat/conversations/{$group}/messages", ['body' => 'Rounds at 9'])->json('data.id');
    $url = "/api/v1/chat/conversations/{$group}/messages/{$message}/reactions";

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
    $secret = $this->postJson("/api/v1/chat/conversations/{$first}/messages", ['body' => 'Only for Sara'])->json('data.id');
    $this->postJson("/api/v1/chat/conversations/{$second}/messages", ['body' => 'Only for Rana'])->assertCreated();

    // Reacting to chat one's message through chat two's address finds nothing.
    $this->postJson("/api/v1/chat/conversations/{$second}/messages/{$secret}/reactions", ['emoji' => '👍'])->assertNotFound();
    $this->getJson("/api/v1/chat/conversations/{$second}/messages")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.body', 'Only for Rana');
    // Nor can a reply quote it from the other chat.
    $this->postJson("/api/v1/chat/conversations/{$second}/messages", ['body' => 'Quoting', 'reply_to_id' => $secret])->assertNotFound();

    Sanctum::actingAs($rana);
    $this->getJson("/api/v1/chat/conversations/{$first}/messages")->assertForbidden();
    expect(collect($this->getJson('/api/v1/chat/conversations')->json('data'))->pluck('id')->all())->toBe([$second]);
});

it('filters the chat list on the server by type, read state and date, with counts', function () {
    $omar = extrasUser();
    $sara = extrasUser();
    $rana = extrasUser();
    $group = extrasGroup($this, $sara, $omar, $rana);
    $this->postJson("/api/v1/chat/conversations/{$group}/messages", ['body' => 'Morning team'])->assertCreated();

    Sanctum::actingAs($omar);
    $direct = $this->postJson('/api/v1/chat/conversations', ['type' => 'direct', 'user_id' => $rana->id])->json('data.id');
    $this->postJson("/api/v1/chat/conversations/{$direct}/messages", ['body' => 'Coffee?'])->assertCreated();

    $ids = fn (string $query): array => collect($this->getJson('/api/v1/chat/conversations'.$query)->assertOk()->json('data'))->pluck('id')->all();
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
    $this->getJson('/api/v1/chat/conversations?type=group')->assertJsonPath('data.0.messages_count', 1)->assertJsonPath('data.0.unread_count', 1);
    $this->getJson('/api/v1/chat/conversations?read=maybe')->assertUnprocessable();
});

it('keeps each user their own chat settings, with defaults until saved', function () {
    $omar = extrasUser();
    Sanctum::actingAs($omar);

    $this->getJson('/api/v1/chat/settings')->assertOk()
        ->assertJsonPath('data.my_color', 'blue')->assertJsonPath('data.others_color', 'green')
        ->assertJsonPath('data.count_in_badge', true)->assertJsonPath('data.reactions.0', '👍')->assertJsonPath('data.all_reactions.6', '😀');
    $this->putJson('/api/v1/chat/settings', ['bubble_style' => 'square', 'my_color' => 'purple', 'count_in_badge' => false])->assertOk()
        ->assertJsonPath('data.bubble_style', 'square')->assertJsonPath('data.my_color', 'purple')->assertJsonPath('data.count_in_badge', false);
    $this->putJson('/api/v1/chat/settings', ['my_color' => 'gold'])->assertUnprocessable();

    Sanctum::actingAs(extrasUser());
    $this->getJson('/api/v1/chat/settings')->assertJsonPath('data.my_color', 'blue');
});

it('lets a user set and remove their own profile photo, shown as a signed link', function () {
    $omar = extrasUser();
    Sanctum::actingAs($omar);

    $url = $this->post('/api/v1/auth/photo', ['photo' => UploadedFile::fake()->image('me.jpg', 300, 300)], ['Accept' => 'application/json'])
        ->assertOk()->json('data.avatar_url');
    expect($url)->toContain('signature=');
    $this->getJson('/api/v1/auth/me')->assertJsonPath('data.user.avatar_url', fn (?string $v) => $v !== null);
    $this->post('/api/v1/auth/photo', ['photo' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertUnprocessable();
    $this->deleteJson('/api/v1/auth/photo')->assertNoContent();
    $this->getJson('/api/v1/auth/me')->assertJsonPath('data.user.avatar_url', null);
});

it('replies to a message of the same chat with a quote of it, which outlives the quoted message', function () {
    $omar = extrasUser();
    $sara = extrasUser();
    $group = extrasGroup($this, $omar, $sara);
    $asked = $this->postJson("/api/v1/chat/conversations/{$group}/messages", ['body' => 'Can someone check bed 4?'])->json('data.id');

    Sanctum::actingAs($sara);
    $reply = $this->postJson("/api/v1/chat/conversations/{$group}/messages", ['body' => 'On it.', 'reply_to_id' => $asked])
        ->assertCreated()
        ->assertJsonPath('data.reply_to.id', $asked)
        ->assertJsonPath('data.reply_to.sender_id', $omar->id)
        ->assertJsonPath('data.reply_to.excerpt', 'Can someone check bed 4?')
        ->json('data.id');
    $this->getJson("/api/v1/chat/conversations/{$group}/messages")->assertOk()->assertJsonPath('data.0.reply_to.id', $asked);
    $this->postJson("/api/v1/chat/conversations/{$group}/messages", ['body' => 'x', 'reply_to_id' => 'not-a-uuid'])->assertUnprocessable();

    // The quoted message goes: the reply stays, without its quote.
    Message::query()->whereKey($asked)->delete();
    $this->getJson("/api/v1/chat/conversations/{$group}/messages")->assertOk()
        ->assertJsonPath('data.0.id', $reply)->assertJsonPath('data.0.reply_to', null);
});

it('soft-deletes chats: gone for members, never removed, listed, readable and restorable by admins only', function () {
    $omar = extrasUser();
    $sara = extrasUser();
    $admin = User::factory()->state(['user_type' => UserType::Admin])->withRole(RoleName::Admin->value)->withTwoFactor()->create();
    $group = extrasGroup($this, $omar, $sara);
    $this->postJson("/api/v1/chat/conversations/{$group}/messages", ['body' => 'Handover at 7'])->assertCreated();

    // Only the leader deletes a group.
    Sanctum::actingAs($sara);
    $this->deleteJson("/api/v1/chat/conversations/{$group}")->assertForbidden();
    $this->getJson('/api/v1/chat/conversations?deleted=1')->assertForbidden();
    Sanctum::actingAs($omar);
    $this->deleteJson("/api/v1/chat/conversations/{$group}")->assertNoContent();
    $this->getJson('/api/v1/chat/conversations')->assertOk()->assertJsonCount(0, 'data');
    $this->postJson("/api/v1/chat/conversations/{$group}/messages", ['body' => 'Still there?'])->assertStatus(409)->assertJsonPath('error_code', 'CHAT_DELETED');
    $this->getJson("/api/v1/chat/conversations/{$group}/messages")->assertStatus(409);
    expect(DB::table('conversations')->where('id', $group)->value('deleted_by'))->toBe($omar->id)
        ->and(DB::table('messages')->where('conversation_id', $group)->count())->toBe(1);

    // The admin sees it with the switch, reads it, and restores it.
    Sanctum::actingAs($admin);
    $this->getJson('/api/v1/chat/conversations?deleted=1')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $group)->assertJsonPath('data.0.deleted_by', $omar->person->full_name);
    $this->getJson("/api/v1/chat/conversations/{$group}/messages")->assertOk()->assertJsonPath('data.0.body', 'Handover at 7');
    $this->postJson("/api/v1/chat/conversations/{$group}/messages", ['body' => 'x'])->assertStatus(409);
    $this->patchJson("/api/v1/chat/conversations/{$group}/restore")->assertOk()->assertJsonPath('data.deleted_at', null);

    Sanctum::actingAs($sara);
    $this->getJson('/api/v1/chat/conversations')->assertOk()->assertJsonCount(1, 'data');
    $this->patchJson("/api/v1/chat/conversations/{$group}/restore")->assertForbidden();

    // Either person deletes a one-to-one chat; starting it again brings it back.
    $direct = $this->postJson('/api/v1/chat/conversations', ['type' => 'direct', 'user_id' => $omar->id])->assertCreated()->json('data.id');
    $this->deleteJson("/api/v1/chat/conversations/{$direct}")->assertNoContent();
    $this->postJson('/api/v1/chat/conversations', ['type' => 'direct', 'user_id' => $omar->id])->assertCreated()
        ->assertJsonPath('data.id', $direct)->assertJsonPath('data.deleted_at', null);
});
