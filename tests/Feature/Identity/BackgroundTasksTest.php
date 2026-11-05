<?php

use App\Enums\Identity\AuditAction;
use App\Enums\Identity\TaskCancelReason;
use App\Enums\Identity\TaskType;
use App\Enums\Shared\GenericStatus;
use App\Exceptions\Shared\ConcurrencyConflictException;
use App\Interfaces\Identity\BackgroundTaskServiceInterface;
use App\Models\Identity\AuditLog;
use App\Models\Identity\BackgroundTask;
use App\Models\Identity\Notification;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');
    // Every export runs as a background task here.
    config(['clinic.transfer.export_sync_limit' => 0]);
    AuditLog::query()->create(['action' => AuditAction::Login, 'module' => 'identity']);
});

function exportAuditLogs(): string
{
    return test()->postJson('/audit-logs/export', ['format' => 'csv', 'filter' => ['date_range' => 'last_7_days']])
        ->assertStatus(202)
        ->json('data.task_id');
}

it('exports the audit trail as a background task and serves the CSV through a signed link', function () {
    $user = actingWith('audit.read_all', 'audit.export');

    $id = exportAuditLogs();

    $this->getJson("/tasks/{$id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.progress', 100)
        ->assertJsonPath('data.has_result', true)
        ->assertJsonPath('data.requested_by.id', $user->id);

    $link = $this->getJson("/tasks/{$id}/download")->assertOk()->json('data');
    expect($link)->toHaveKeys(['url', 'expires_at']);

    $file = $this->get($link['url'])->assertOk();
    expect($file->headers->get('content-disposition'))->toContain('audit-')
        ->and($file->streamedContent())->toContain('Time,User,Action,Module,Record,"Old values","New values","IP address","Request id"');

    // The owner gets an in-app notification.
    expect(Notification::query()->where('user_id', $user->id)->where('template', 'task.completed')->exists())->toBeTrue();
    $this->getJson('/notifications/mine')->assertOk()->assertJsonPath('meta.unread_count', 1);
});

it('rejects tampered or expired file links', function () {
    actingWith('audit.read_all', 'audit.export');
    $url = $this->getJson('/tasks/'.exportAuditLogs().'/download')->json('data.url');

    $this->get($url.'x')->assertForbidden();
    $this->travel(6)->minutes();
    $this->get($url)->assertForbidden();
});

it('requires the export permission', function () {
    actingWith('audit.read_all');

    $this->postJson('/audit-logs/export', ['format' => 'csv'])->assertForbidden()->assertJsonPath('error_code', 'TRANSFER_NOT_ALLOWED');
});

it('shows users only their own tasks', function () {
    $owner = actingWith('audit.read_all', 'audit.export');
    $id = exportAuditLogs();

    actingWith('audit.read_all');
    $this->getJson("/tasks/{$id}")->assertNotFound();
    $this->getJson('/tasks')->assertOk()->assertJsonCount(0, 'data');

    Sanctum::actingAs($owner);
    $this->getJson('/tasks?filter[type][eq]=list_export')->assertOk()->assertJsonCount(1, 'data');
});

it('cancels pending tasks and refuses finished ones', function () {
    $user = actingWith('audit.read_all');
    $task = BackgroundTask::query()->create(['type' => TaskType::AuditExport, 'parameters' => [], 'requested_by' => $user->id]);

    $this->postJson("/tasks/{$task->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancel_reason', 'user');

    $this->postJson("/tasks/{$task->id}/cancel")->assertStatus(409)->assertJsonPath('error_code', 'TASK_NOT_CANCELLABLE');
    $this->getJson("/tasks/{$task->id}/download")->assertStatus(409)->assertJsonPath('error_code', 'TASK_RESULT_NOT_READY');
});

it('reports an expired result', function () {
    actingWith('audit.read_all', 'audit.export');
    $id = exportAuditLogs();

    $this->travel(8)->days();
    $this->getJson("/tasks/{$id}/download")->assertStatus(410)->assertJsonPath('error_code', 'TASK_RESULT_EXPIRED');

    $this->artisan('tasks:purge-results')->assertSuccessful();
    expect(BackgroundTask::query()->find($id)?->result_path)->toBeNull();
});

it('blocks a second running task on the same subject', function () {
    $user = User::factory()->create();
    $service = app(BackgroundTaskServiceInterface::class);
    BackgroundTask::query()->create(['type' => TaskType::AttachmentsZip, 'parameters' => [], 'requested_by' => $user->id, 'subject_type' => 'user', 'subject_id' => $user->id]);

    expect(fn () => $service->dispatch(TaskType::AttachmentsZip, [], $user, $user))->toThrow(ConcurrencyConflictException::class);
});

it('marks a failing task as cancelled with reason failed and notifies the owner', function () {
    $user = actingWith('audit.read_all');

    // No report handler is registered yet, so the job throws (the sync queue re-throws after failed()).
    expect(fn () => app(BackgroundTaskServiceInterface::class)->dispatch(TaskType::ReportExport, [], $user))->toThrow(LogicException::class);

    $task = BackgroundTask::query()->sole();
    expect($task->status)->toBe(GenericStatus::Cancelled)
        ->and($task->cancel_reason)->toBe(TaskCancelReason::Failed)
        ->and(Notification::query()->where('template', 'task.failed')->count())->toBe(1);
});
