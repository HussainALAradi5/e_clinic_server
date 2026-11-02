<?php

use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Models\Identity\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('makes whoever solves a ticket nobody took its assignee', function () {
    $nurse = User::factory()->state(['user_type' => UserType::MedicalStaff])->withRole(RoleName::Nurse->value)->create();
    $admin = User::factory()->state(['user_type' => UserType::Admin])->withRole(RoleName::Admin->value)->withTwoFactor()->create();
    Sanctum::actingAs($nurse);
    $ticket = $this->postJson('/api/v1/support/tickets', [
        'subject' => 'Printer jams', 'description' => 'The exam room printer jams on every certificate.', 'category' => 'equipment',
    ])->assertCreated()->json('data');

    Sanctum::actingAs($admin);
    $this->patchJson("/api/v1/support/tickets/{$ticket['id']}/status", ['status' => 'completed', 'resolution' => 'Set the paper guide to A5.', 'version' => 1])
        ->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.assignee.id', $admin->id)->assertJsonPath('data.resolver.id', $admin->id);
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
    ])->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.priority', 'high')->json('data');
    expect($ticket['code'])->toStartWith('TKT-')->and($notified('ticket.opened', $admin))->toBeTrue();
    $this->patchJson("/api/v1/support/tickets/{$ticket['id']}/status", ['status' => 'completed', 'resolution' => 'x', 'version' => 1])->assertForbidden();

    // A colleague does not see it.
    Sanctum::actingAs($colleague);
    $this->getJson("/api/v1/support/tickets/{$ticket['id']}")->assertNotFound();
    $this->getJson('/api/v1/support/tickets')->assertOk()->assertJsonCount(0, 'data');

    // The admin takes it, answers and resolves it (a resolution is required).
    Sanctum::actingAs($admin);
    $this->getJson('/api/v1/support/tickets')->assertOk()->assertJsonCount(1, 'data');
    $this->patchJson("/api/v1/support/tickets/{$ticket['id']}/status", ['status' => 'in_progress', 'version' => 1])
        ->assertOk()->assertJsonPath('data.assignee.id', $admin->id)->assertJsonPath('data.allowed_transitions', ['pending', 'completed', 'cancelled']);
    $this->postJson("/api/v1/support/tickets/{$ticket['id']}/comments", ['body' => 'On my way with a new cartridge.'])
        ->assertCreated()->assertJsonPath('data.author.is_support', true);
    expect($notified('ticket.replied', $nurse))->toBeTrue();
    // A reply may bump the version (it touches the ticket): read the current one.
    $v = $this->getJson("/api/v1/support/tickets/{$ticket['id']}")->json('data.version');
    $this->patchJson("/api/v1/support/tickets/{$ticket['id']}/status", ['status' => 'completed', 'version' => $v])->assertUnprocessable()->assertJsonPath('error_code', 'TICKET_RESOLUTION_REQUIRED');
    $steps = ['Replaced the toner cartridge.', ' ', 'Printed a test label.'];
    // Steps alone are not enough: the solution in words is required.
    $this->patchJson("/api/v1/support/tickets/{$ticket['id']}/status", ['status' => 'completed', 'steps' => $steps, 'version' => $v])->assertUnprocessable()->assertJsonPath('error_code', 'TICKET_RESOLUTION_REQUIRED');
    $solved = ['status' => 'completed', 'resolution' => 'The toner was empty; replaced it.', 'steps' => $steps];
    $this->patchJson("/api/v1/support/tickets/{$ticket['id']}/status", [...$solved, 'version' => $v - 1])->assertStatus(409);
    $this->patchJson("/api/v1/support/tickets/{$ticket['id']}/status", [...$solved, 'version' => $v])
        ->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.allowed_transitions', ['pending'])->assertJsonPath('data.resolver.id', $admin->id)
        ->assertJsonPath('data.resolution', 'The toner was empty; replaced it.')
        ->assertJsonPath('data.resolution_steps', ['Replaced the toner cartridge.', 'Printed a test label.'])
        ->assertJsonPath('data.confirmation', 'awaiting');
    expect($notified('ticket.resolved', $nurse))->toBeTrue();
    // Only the requester answers.
    $this->postJson("/api/v1/support/tickets/{$ticket['id']}/confirm")->assertForbidden();

    // A reply does not reopen it; the nurse declines with a reason: back to the admin, in progress.
    Sanctum::actingAs($nurse);
    $this->postJson("/api/v1/support/tickets/{$ticket['id']}/comments", ['body' => 'Testing it now.'])->assertCreated();
    $this->getJson("/api/v1/support/tickets/{$ticket['id']}")->assertOk()->assertJsonPath('data.status', 'completed');
    $this->postJson("/api/v1/support/tickets/{$ticket['id']}/decline", ['reason' => 'no'])->assertUnprocessable();
    $this->postJson("/api/v1/support/tickets/{$ticket['id']}/decline", ['reason' => 'Red light is back.'])->assertOk()
        ->assertJsonPath('data.status', 'in_progress')->assertJsonPath('data.confirmation', 'declined')
        ->assertJsonPath('data.assignee.id', $admin->id)->assertJsonPath('data.comments.2.body', 'Not fixed yet: Red light is back.');
    expect($notified('ticket.declined', $admin))->toBeTrue();
    $this->postJson("/api/v1/support/tickets/{$ticket['id']}/confirm")->assertStatus(409);

    // Solved again; this time the nurse confirms and the admin is told.
    Sanctum::actingAs($admin);
    $version = $this->getJson("/api/v1/support/tickets/{$ticket['id']}")->json('data.version');
    // A solution without steps is fine.
    $this->patchJson("/api/v1/support/tickets/{$ticket['id']}/status", ['status' => 'completed', 'resolution' => 'Replaced the fuser unit.', 'version' => $version])
        ->assertOk()->assertJsonPath('data.resolution_steps', []);
    Sanctum::actingAs($nurse);
    $this->postJson("/api/v1/support/tickets/{$ticket['id']}/confirm")->assertOk()
        ->assertJsonPath('data.confirmation', 'confirmed')->assertJsonPath('data.status', 'completed');
    expect($notified('ticket.confirmed', $admin))->toBeTrue();

    // Reopened and cancelled by the admin; cancelled is final.
    Sanctum::actingAs($admin);
    $version = $this->getJson("/api/v1/support/tickets/{$ticket['id']}")->json('data.version');
    $version = $this->patchJson("/api/v1/support/tickets/{$ticket['id']}/status", ['status' => 'pending', 'version' => $version])->assertOk()
        ->assertJsonPath('data.confirmation', null)->json('data.version');
    $this->patchJson("/api/v1/support/tickets/{$ticket['id']}/status", ['status' => 'cancelled', 'version' => $version])->assertOk()->assertJsonPath('data.allowed_transitions', []);
    $this->postJson("/api/v1/support/tickets/{$ticket['id']}/comments", ['body' => 'More'])->assertStatus(409);
});

it('takes screenshots and photos on a ticket from its requester and the desk, but not from others', function () {
    Storage::fake(config('filesystems.default'));
    $nurse = User::factory()->state(['user_type' => UserType::MedicalStaff])->withRole(RoleName::Nurse->value)->create();
    $other = User::factory()->state(['user_type' => UserType::Doctor])->withRole(RoleName::Doctor->value)->create();

    Sanctum::actingAs($nurse);
    $ticket = $this->postJson('/api/v1/support/tickets', ['subject' => 'Screen flickers', 'description' => 'The reception screen flickers all the time.', 'category' => 'equipment'])
        ->assertCreated()->assertJsonPath('data.priority', 'medium')->json('data.id');
    $upload = fn (UploadedFile $file) => $this->post('/api/v1/attachments', [
        'attachable_type' => 'support_ticket', 'attachable_id' => $ticket, 'category' => 'ticket_attachment', 'files' => [$file],
    ], ['Accept' => 'application/json']);
    $upload(UploadedFile::fake()->image('screen.png', 400, 300))->assertSuccessful();
    $upload(UploadedFile::fake()->create('notes.docx', 5, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))->assertUnprocessable();
    $this->getJson("/api/v1/support/tickets/{$ticket}")->assertOk()->assertJsonCount(1, 'data.attachments')->assertJsonPath('data.attachments.0.original_name', 'screen.png');

    Sanctum::actingAs($other);
    $upload(UploadedFile::fake()->image('mine.png', 100, 100))->assertNotFound();
});

it('publishes the ticket enums with their labels, even after the enum list was cached before', function () {
    $nurse = User::factory()->state(['user_type' => UserType::MedicalStaff])->withRole(RoleName::Nurse->value)->create();
    // A list cached under the old key (before the enums were added) must not hide them.
    Cache::forever('meta:enums:en', ['GenericStatus' => []]);

    Sanctum::actingAs($nurse);
    $this->getJson('/api/v1/meta/enums')->assertOk()
        ->assertJsonPath('data.TicketCategory.options.2.label', 'Equipment')
        ->assertJsonPath('data.TicketConfirmation.options.0.value', 'awaiting')
        ->assertJsonPath('data.TreatmentPriority.options.0.value', 'medium');
});

it('shows each person only their own tickets, while the support team sees, takes and solves every one', function () {
    $nurse = User::factory()->state(['user_type' => UserType::MedicalStaff])->withRole(RoleName::Nurse->value)->create();
    $doctor = User::factory()->state(['user_type' => UserType::Doctor])->withRole(RoleName::Doctor->value)->create();
    $support = User::factory()->state(['user_type' => UserType::Admin])->withRole(RoleName::Support->value)->create();
    $open = function (User $who, string $subject): string {
        Sanctum::actingAs($who);

        return $this->postJson('/api/v1/support/tickets', [
            'subject' => $subject, 'description' => 'Something on my screen is not working as it should.', 'category' => 'technical',
        ])->assertCreated()->json('data.id');
    };

    $mine = $open($nurse, 'Nurse station screen');
    $open($doctor, 'Doctor laptop is slow');

    Sanctum::actingAs($nurse);
    $this->getJson('/api/v1/support/tickets')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.subject', 'Nurse station screen');
    Sanctum::actingAs($doctor);
    $this->getJson("/api/v1/support/tickets/{$mine}")->assertNotFound();

    Sanctum::actingAs($support);
    $this->getJson('/api/v1/support/tickets')->assertOk()->assertJsonCount(2, 'data');
    $this->patchJson("/api/v1/support/tickets/{$mine}/status", ['status' => 'in_progress', 'version' => 1])
        ->assertOk()->assertJsonPath('data.assignee.id', $support->id);
    $this->postJson("/api/v1/support/tickets/{$mine}/comments", ['body' => 'Coming over now.'])
        ->assertCreated()->assertJsonPath('data.author.is_support', true);
});
