<?php

use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Modules\Identity\Users\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lets staff open and follow their own tickets while only admins move and resolve them, with notifications', function () {
    $nurse = User::factory()->state(['user_type' => UserType::MedicalStaff])->withRole(RoleName::Nurse->value)->create();
    $colleague = User::factory()->state(['user_type' => UserType::Doctor])->withRole(RoleName::Doctor->value)->create();
    $admin = User::factory()->state(['user_type' => UserType::Admin])->withRole(RoleName::Admin->value)->withTwoFactor()->create();
    $notified = fn (string $template, User $to): bool => DB::table('notifications')->where('template', $template)->where('person_id', $to->id)->exists();

    Sanctum::actingAs($nurse);
    $this->postJson('/api/v1/support/tickets', ['subject' => 'Hi', 'description' => 'short', 'category' => 'nope'])
        ->assertUnprocessable()->assertJsonValidationErrors(['subject', 'description', 'category']);
    $ticket = $this->postJson('/api/v1/support/tickets', [
        'subject' => 'Label printer offline', 'description' => 'The ward 2 label printer shows a red light since 8:00.', 'category' => 'equipment', 'priority' => 'high',
    ])->assertCreated()->assertJsonPath('data.status', 'open')->assertJsonPath('data.priority', 'high')->json('data');
    expect($ticket['code'])->toStartWith('TKT-')->and($notified('ticket.opened', $admin))->toBeTrue();
    $this->patchJson("/api/v1/support/tickets/{$ticket['id']}/status", ['status' => 'resolved', 'resolution' => 'x', 'version' => 1])->assertForbidden();

    // A colleague does not see it.
    Sanctum::actingAs($colleague);
    $this->getJson("/api/v1/support/tickets/{$ticket['id']}")->assertNotFound();
    $this->getJson('/api/v1/support/tickets')->assertOk()->assertJsonCount(0, 'data');

    // The admin takes it, answers and resolves it (a resolution is required).
    Sanctum::actingAs($admin);
    $this->getJson('/api/v1/support/tickets')->assertOk()->assertJsonCount(1, 'data');
    $this->patchJson("/api/v1/support/tickets/{$ticket['id']}/status", ['status' => 'in_progress', 'version' => 1])
        ->assertOk()->assertJsonPath('data.assignee.id', $admin->id)->assertJsonPath('data.allowed_transitions', ['open', 'resolved', 'closed']);
    $this->postJson("/api/v1/support/tickets/{$ticket['id']}/comments", ['body' => 'On my way with a new cartridge.'])
        ->assertCreated()->assertJsonPath('data.author.is_support', true);
    expect($notified('ticket.replied', $nurse))->toBeTrue();
    $this->patchJson("/api/v1/support/tickets/{$ticket['id']}/status", ['status' => 'resolved', 'version' => 2])->assertUnprocessable()->assertJsonPath('error_code', 'TICKET_RESOLUTION_REQUIRED');
    $this->patchJson("/api/v1/support/tickets/{$ticket['id']}/status", ['status' => 'resolved', 'resolution' => 'Cartridge replaced.', 'version' => 1])->assertStatus(409);
    $this->patchJson("/api/v1/support/tickets/{$ticket['id']}/status", ['status' => 'resolved', 'resolution' => 'Cartridge replaced.', 'version' => 2])
        ->assertOk()->assertJsonPath('data.status', 'resolved')->assertJsonPath('data.resolver.id', $admin->id);
    expect($notified('ticket.resolved', $nurse))->toBeTrue();

    // Still broken: the nurse answers and the ticket opens again; closed is final.
    Sanctum::actingAs($nurse);
    $this->postJson("/api/v1/support/tickets/{$ticket['id']}/comments", ['body' => 'Red light is back.'])->assertCreated();
    $this->getJson("/api/v1/support/tickets/{$ticket['id']}")->assertOk()->assertJsonPath('data.status', 'open')->assertJsonCount(2, 'data.comments');
    Sanctum::actingAs($admin);
    $this->patchJson("/api/v1/support/tickets/{$ticket['id']}/status", ['status' => 'closed', 'version' => 4])->assertOk();
    $this->postJson("/api/v1/support/tickets/{$ticket['id']}/comments", ['body' => 'More'])->assertStatus(409);
});
