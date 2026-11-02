<?php

declare(strict_types=1);

namespace App\Tasks\Identity\Services;

use App\Enums\Identity\AuditAction;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\TaskCancelReason;
use App\Enums\Identity\TaskType;
use App\Enums\Shared\GenericStatus;
use App\Exceptions\Shared\ConcurrencyConflictException;
use App\Interfaces\Identity\AuditLogServiceInterface;
use App\Models\Identity\User;
use App\Services\Shared\FileStorage;
use App\Tasks\Identity\Contracts\BackgroundTaskServiceInterface;
use App\Tasks\Identity\Events\TaskCancelled;
use App\Tasks\Identity\Events\TaskCompleted;
use App\Tasks\Identity\Exceptions\TaskNotCancellableException;
use App\Tasks\Identity\Exceptions\TaskResultExpiredException;
use App\Tasks\Identity\Exceptions\TaskResultNotReadyException;
use App\Tasks\Identity\Jobs\RunBackgroundTask;
use App\Tasks\Identity\Models\BackgroundTask;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class BackgroundTaskService implements BackgroundTaskServiceInterface
{
    private const PROGRESS_THROTTLE_SECONDS = 2;

    public function __construct(
        private readonly ApplyListQuery $listQuery,
        private readonly FileStorage $files,
        private readonly ClinicClock $clock,
        private readonly AuditLogServiceInterface $audit,
    ) {}

    public function start(string $type, array $parameters, Authenticatable $user, ?Model $subject = null): Model
    {
        if (! $user instanceof User) {
            throw new InvalidArgumentException('Background tasks are started by users.');
        }

        return $this->dispatch(TaskType::from($type), $parameters, $user, $subject);
    }

    public function dispatch(TaskType $type, array $parameters, User $user, ?Model $subject = null): BackgroundTask
    {
        return DB::transaction(function () use ($type, $parameters, $user, $subject): BackgroundTask {
            if ($subject !== null && BackgroundTask::query()->open()->where([
                'type' => $type,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
            ])->lockForUpdate()->exists()) {
                throw new ConcurrencyConflictException(context: ['reason' => 'task_already_running']);
            }

            $task = BackgroundTask::query()->create([
                'type' => $type,
                'status' => GenericStatus::Pending,
                'parameters' => $parameters,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'requested_by' => $user->id,
            ]);

            RunBackgroundTask::dispatch($task->id)->onQueue(Config::get("clinic.tasks.queues.{$type->value}"))->afterCommit();

            return $task;
        });
    }

    public function markInProgress(BackgroundTask $task): bool
    {
        $task->refresh();
        if ($task->status !== GenericStatus::Pending) {
            return false;
        }

        $task->started_at = $this->clock->now();
        $task->start();

        return true;
    }

    public function reportProgress(BackgroundTask $task, int $percent): void
    {
        $percent = max(0, min(99, $percent));
        $key = "task-progress:{$task->id}";

        if ($percent <= $task->progress || ! Cache::add($key, true, self::PROGRESS_THROTTLE_SECONDS)) {
            return;
        }

        BackgroundTask::query()->whereKey($task->id)->where('status', GenericStatus::InProgress)->update(['progress' => $percent]);
        $task->progress = $percent;
    }

    public function complete(BackgroundTask $task, ?string $resultPath = null, array $summary = []): void
    {
        $now = $this->clock->now();
        $task->forceFill([
            'progress' => 100,
            'result_path' => $resultPath,
            'result_summary' => $summary,
            'finished_at' => $now,
            'expires_at' => $resultPath !== null ? $now->addDays(Config::integer('clinic.tasks.result_days')) : null,
        ]);
        $task->complete();

        TaskCompleted::dispatch($task);
    }

    public function cancel(BackgroundTask $task, TaskCancelReason $reason, ?string $error = null): BackgroundTask
    {
        $task = DB::transaction(function () use ($task, $reason, $error): BackgroundTask {
            $task = BackgroundTask::query()->lockForUpdate()->findOrFail($task->id);
            if ($task->isFinal()) {
                throw new TaskNotCancellableException;
            }

            $task->forceFill([
                'cancel_reason' => $reason,
                'error_message' => $error !== null ? Str::limit($error, 990) : null,
                'finished_at' => $this->clock->now(),
            ]);
            $task->cancel();

            return $task;
        });

        $this->files->delete($task->result_path);
        TaskCancelled::dispatch($task);

        return $task;
    }

    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->visible(), $query);
    }

    public function find(string $id): BackgroundTask
    {
        return $this->visible()->findOrFail($id);
    }

    public function download(BackgroundTask $task): array
    {
        if ($task->status !== GenericStatus::Completed || $task->result_path === null) {
            throw new TaskResultNotReadyException;
        }
        if (($task->expires_at !== null && $task->expires_at->isPast()) || ! $this->files->disk()->exists($task->result_path)) {
            throw new TaskResultExpiredException;
        }

        $this->audit->record(AuditAction::Exported, $task, [], ['type' => $task->type->value], 'identity');

        $name = (string) ($task->parameters['download_name'] ?? $task->type->value.'-'.$task->created_at->format('Ymd-His').'.'.pathinfo($task->result_path, PATHINFO_EXTENSION));

        return $this->files->temporaryUrl($task->result_path, $name);
    }

    /**
     * Own tasks; super admins and admins see every task.
     *
     * @return Builder<BackgroundTask>
     */
    private function visible(): Builder
    {
        $user = Auth::user();
        $query = BackgroundTask::query()->with('requester.person');

        if ($user instanceof User && $user->hasAnyRole([RoleName::SuperAdmin->value, RoleName::Admin->value])) {
            return $query;
        }

        return $user === null ? $query->whereRaw('1 = 0') : $query->where('requested_by', $user->getAuthIdentifier());
    }
}
