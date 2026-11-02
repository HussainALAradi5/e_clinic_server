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
