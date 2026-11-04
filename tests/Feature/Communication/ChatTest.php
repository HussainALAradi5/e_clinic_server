<?php

use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Events\Communication\ConversationRead;
use App\Events\Communication\MessageSent;
use App\Models\Identity\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function chatUser(RoleName $role = RoleName::Doctor, UserType $type = UserType::Doctor): User
{
    return User::factory()->state(['user_type' => $type])->withRole($role->value)->create();
}

it('keeps one direct chat per pair, delivers messages live and counts what is unread', function () {
    Event::fake([MessageSent::class, ConversationRead::class]);
    $omar = chatUser();
    $rana = chatUser(RoleName::Receptionist, UserType::Receptionist);

    Sanctum::actingAs($omar);
    $chat = $this->postJson('/api/v1/chat/conversations', ['type' => 'direct', 'user_id' => $rana->id])->assertCreated()
        ->assertJsonPath('data.type', 'direct')->assertJsonPath('data.title', $rana->person->full_name)->json('data.id');
    $this->postJson('/api/v1/chat/conversations', ['type' => 'direct', 'user_id' => $rana->id])->assertCreated()->assertJsonPath('data.id', $chat);
    $this->postJson("/api/v1/chat/conversations/{$chat}/messages", ['body' => '  Is Mr Ali here yet?  '])->assertCreated()->assertJsonPath('data.body', 'Is Mr Ali here yet?');
    Event::assertDispatched(MessageSent::class, fn (MessageSent $e): bool => in_array($rana->id, $e->memberIds, true));

    Sanctum::actingAs($rana);
    $this->getJson('/api/v1/chat/unread')->assertOk()->assertJsonPath('data.count', 1);
    $this->getJson('/api/v1/chat/conversations')->assertOk()->assertJsonPath('data.0.unread_count', 1)
        ->assertJsonPath('data.0.title', $omar->person->full_name)->assertJsonPath('data.0.last_message.body', 'Is Mr Ali here yet?');
    $this->postJson("/api/v1/chat/conversations/{$chat}/read")->assertNoContent();
    $this->getJson('/api/v1/chat/unread')->assertOk()->assertJsonPath('data.count', 0);
    $this->getJson("/api/v1/chat/conversations/{$chat}/messages")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.has_more', false);
});

it('lets only members read a chat and keeps patients and inactive accounts out', function () {
    $omar = chatUser();
    $sara = chatUser();
    $outsider = chatUser();
    $patient = User::factory()->state(['user_type' => UserType::Patient])->withRole(RoleName::Patient->value)->create();

    Sanctum::actingAs($omar);
    $chat = $this->postJson('/api/v1/chat/conversations', ['type' => 'direct', 'user_id' => $sara->id])->json('data.id');
    $this->postJson('/api/v1/chat/conversations', ['type' => 'direct', 'user_id' => $patient->id])->assertUnprocessable()->assertJsonPath('error_code', 'CHAT_INVALID_PARTNER');
    $this->postJson('/api/v1/chat/conversations', ['type' => 'direct', 'user_id' => $omar->id])->assertUnprocessable();
    expect(collect($this->getJson('/api/v1/chat/contacts')->json('data'))->pluck('id'))->toContain($sara->id)->not->toContain($patient->id, $omar->id);

    Sanctum::actingAs($outsider);
    $this->getJson("/api/v1/chat/conversations/{$chat}/messages")->assertForbidden()->assertJsonPath('error_code', 'CHAT_NOT_A_MEMBER');
    $this->postJson("/api/v1/chat/conversations/{$chat}/messages", ['body' => 'Hi'])->assertForbidden();

    Sanctum::actingAs($patient);
    $this->getJson('/api/v1/chat/conversations')->assertForbidden();
});

it('runs a group: admins rename, add, promote and remove; members leave; a group always keeps an admin', function () {
    $omar = chatUser();
    $sara = chatUser();
    $maria = chatUser(RoleName::Nurse, UserType::MedicalStaff);
    $yusuf = chatUser(RoleName::Pharmacist, UserType::Pharmacist);

    Sanctum::actingAs($omar);
    $group = $this->postJson('/api/v1/chat/conversations', ['type' => 'group', 'title' => 'Ward round', 'member_ids' => [$sara->id, $maria->id]])
        ->assertCreated()->assertJsonPath('data.my_role', 'admin')->assertJsonCount(3, 'data.members')->json('data.id');
    $members = collect($this->getJson("/api/v1/chat/conversations/{$group}")->json('data.members'))->keyBy('user_id');
    expect($members[$omar->id]['role'])->toBe('admin')->and($members[$omar->id]['is_me'])->toBeTrue()
        ->and($members[$sara->id]['role'])->toBe('member')->and($members[$sara->id]['is_me'])->toBeFalse();

    // A member cannot change the group.
    Sanctum::actingAs($sara);
    $this->patchJson("/api/v1/chat/conversations/{$group}", ['title' => 'Mine'])->assertForbidden()->assertJsonPath('error_code', 'CHAT_GROUP_ADMIN_REQUIRED');
    $this->postJson("/api/v1/chat/conversations/{$group}/members", ['user_ids' => [$yusuf->id]])->assertForbidden();

    Sanctum::actingAs($omar);
    $this->patchJson("/api/v1/chat/conversations/{$group}", ['title' => 'Morning ward round'])->assertOk()->assertJsonPath('data.title', 'Morning ward round');
    $this->postJson("/api/v1/chat/conversations/{$group}/members", ['user_ids' => [$yusuf->id]])->assertOk()->assertJsonCount(4, 'data.members');
    $this->patchJson("/api/v1/chat/conversations/{$group}/members/{$omar->id}", ['role' => 'member'])->assertForbidden()->assertJsonPath('error_code', 'CHAT_GROUP_ADMIN_REQUIRED');
    $this->patchJson("/api/v1/chat/conversations/{$group}/members/{$sara->id}", ['role' => 'admin'])->assertOk();
    $this->deleteJson("/api/v1/chat/conversations/{$group}/members/{$yusuf->id}")->assertNoContent();

    // Maria leaves on her own; the removed pharmacist no longer reads it.
    Sanctum::actingAs($maria);
    $this->deleteJson("/api/v1/chat/conversations/{$group}/members/{$maria->id}")->assertNoContent();
    $this->getJson("/api/v1/chat/conversations/{$group}")->assertForbidden();
    Sanctum::actingAs($yusuf);
    $this->getJson("/api/v1/chat/conversations/{$group}")->assertForbidden();

    // When the last admins leave, the longest-standing member takes over.
    Sanctum::actingAs($omar);
    $this->deleteJson("/api/v1/chat/conversations/{$group}/members/{$omar->id}")->assertNoContent();
    Sanctum::actingAs($sara);
    $this->getJson("/api/v1/chat/conversations/{$group}")->assertOk()->assertJsonPath('data.my_role', 'admin');
});

it('finds colleagues to message by name, phone, email or staff no., a page at a time', function () {
    $me = chatUser();
    $omar = chatUser();
    $omar->person->forceFill(['first_name' => 'Omar', 'last_name' => 'Khalil', 'phone' => '+97336001122', 'email' => 'omar.k@clinic.test'])->save();
    foreach (range(1, 4) as $i) {
        chatUser();
    }
    $patient = User::factory()->state(['user_type' => UserType::Patient])->withRole(RoleName::Patient->value)->create();
    $patient->person->forceFill(['first_name' => 'Omar', 'phone' => '+97336009999'])->save();

    Sanctum::actingAs($me);
    foreach (['omar kh', '36001122', 'omar.k@clinic', $omar->person->code] as $search) {
        $this->getJson('/api/v1/chat/contacts?search='.urlencode($search))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $omar->id)->assertJsonPath('meta.total', 1);
    }
    $this->getJson('/api/v1/chat/contacts?search=nobody-here')->assertOk()->assertJsonCount(0, 'data');

    $first = $this->getJson('/api/v1/chat/contacts?per_page=2')->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 5)->assertJsonPath('meta.last_page', 3)->json('data');
    $second = $this->getJson('/api/v1/chat/contacts?per_page=2&page=2')->assertOk()->assertJsonCount(2, 'data')->json('data');
    expect(array_intersect(array_column($first, 'id'), array_column($second, 'id')))->toBe([]);
    $this->getJson('/api/v1/chat/contacts?per_page=500')->assertUnprocessable();
});

it('mutes a chat for one member: no badge from it, ends by itself, and shows the colleague contact in the info', function () {
    Event::fake([MessageSent::class, ConversationRead::class]);
    $omar = chatUser();
    $rana = chatUser(RoleName::Receptionist, UserType::Receptionist);
    Sanctum::actingAs($omar);
    $chat = $this->postJson('/api/v1/chat/conversations', ['type' => 'direct', 'user_id' => $rana->id])->json('data.id');
    $this->postJson("/api/v1/chat/conversations/{$chat}/messages", ['body' => 'Lunch?'])->assertCreated();

    Sanctum::actingAs($rana);
    $this->patchJson("/api/v1/chat/conversations/{$chat}/mute", ['duration' => 'forever'])->assertUnprocessable();
    $this->patchJson("/api/v1/chat/conversations/{$chat}/mute", ['duration' => '8h'])->assertOk()
        ->assertJsonPath('data.muted', true)
        ->assertJsonPath('data.members.0.email', fn ($email) => $email !== null);
    // The chat keeps its own count; the menu badge leaves it out.
    $this->getJson('/api/v1/chat/unread')->assertJsonPath('data.count', 0);
    $this->getJson('/api/v1/chat/conversations')->assertJsonPath('data.0.unread_count', 1)->assertJsonPath('data.0.muted', true);

    // Omar's copy is not muted.
    Sanctum::actingAs($omar);
    $this->getJson("/api/v1/chat/conversations/{$chat}")->assertJsonPath('data.muted', false);

    Sanctum::actingAs($rana);
    $this->travel(9)->hours();
    $this->getJson('/api/v1/chat/unread')->assertJsonPath('data.count', 1);
    $this->getJson("/api/v1/chat/conversations/{$chat}")->assertJsonPath('data.muted', false);

    $this->patchJson("/api/v1/chat/conversations/{$chat}/mute", ['duration' => 'always'])->assertJsonPath('data.muted', true)->assertJsonPath('data.muted_until', null);
    $this->patchJson("/api/v1/chat/conversations/{$chat}/mute", ['duration' => null])->assertJsonPath('data.muted', false);
});
