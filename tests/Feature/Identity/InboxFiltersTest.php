<?php

use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Modules\Identity\Notifications\Models\Notification;
use App\Modules\Identity\Users\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/** One delivered in-app notice for `$user`. */
function inboxNotice(User $user, string $template, string $subject, string $relatedType, bool $read, string $at): Notification
{
    return Notification::query()->create([
        'person_id' => $user->id,
        'user_id' => $user->id,
        'channel' => 'in_app',
        'template' => $template,
        'subject' => $subject,
        'body' => $subject,
        'related_type' => $relatedType,
        'related_id' => (string) Str::uuid(),
        'status' => 'delivered',
        'scheduled_for' => $at,
        'delivered_at' => $at,
        'read_at' => $read ? $at : null,
        'created_at' => $at,
    ]);
}

it('filters my inbox on the server by read state, what it is about, text and date, and pages it', function () {
    $me = User::factory()->state(['user_type' => UserType::MedicalStaff])->withRole(RoleName::Nurse->value)->create();
    $other = User::factory()->state(['user_type' => UserType::Doctor])->withRole(RoleName::Doctor->value)->create();
    inboxNotice($me, 'ticket.resolved', 'Ticket TKT-1 resolved', 'support_ticket', false, '2026-10-30 09:00:00');
    inboxNotice($me, 'ticket.replied', 'New reply on ticket TKT-1', 'support_ticket', true, '2026-10-29 09:00:00');
    inboxNotice($me, 'chat.mention', 'Omar mentioned you', 'conversation', false, '2026-10-28 09:00:00');
    inboxNotice($other, 'ticket.opened', 'Not mine', 'support_ticket', false, '2026-10-30 09:00:00');

    Sanctum::actingAs($me);
    $subjects = fn (string $query): array => collect($this->getJson('/api/v1/notifications/mine'.$query)->assertOk()->json('data'))->pluck('subject')->all();

    expect($subjects(''))->toHaveCount(3)->not->toContain('Not mine')
        ->and($subjects('?read=unread'))->toEqualCanonicalizing(['Ticket TKT-1 resolved', 'Omar mentioned you'])
        ->and($subjects('?read=read'))->toBe(['New reply on ticket TKT-1'])
        ->and($subjects('?'.http_build_query(['filter' => ['and' => [['related_type' => ['eq' => 'support_ticket']]]]])))->toHaveCount(2)
        ->and($subjects('?search=mentioned'))->toBe(['Omar mentioned you'])
        ->and($subjects('?date_field=created_at&date_from=2026-10-29&date_to=2026-10-29'))->toBe(['New reply on ticket TKT-1']);
    $this->getJson('/api/v1/notifications/mine?per_page=2')->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3)->assertJsonPath('meta.unread_count', 2);
    $this->getJson('/api/v1/notifications/mine?read=maybe')->assertUnprocessable();
});
