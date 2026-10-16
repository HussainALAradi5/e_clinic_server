<?php

declare(strict_types=1);

namespace App\Modules\Identity\Tasks\Jobs;

use App\Enums\Identity\TaskCancelReason;
use App\Modules\Identity\Tasks\Contracts\BackgroundTaskServiceInterface;
use App\Modules\Identity\Tasks\Models\BackgroundTask;
use App\Modules\Identity\Tasks\Support\RunningTask;
use App\Modules\Identity\Tasks\Support\TaskHandlerRegistry;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Files\FileStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * Runs one background task with the rights of the user who started it.
 * Failures and timeouts end the task as `cancelled` with reason failed / timeout (owner is notified).
 */
final class RunBackgroundTask implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $taskId)
    {
        $this->timeout = Config::integer('clinic.tasks.timeout_seconds');
    }

    public function handle(BackgroundTaskServiceInterface $tasks, TaskHandlerRegistry $registry, FileStorage $files): void
    {
        $task = BackgroundTask::query()->find($this->taskId);
        $user = $task?->requester;

        if ($task === null || $user === null || ! $tasks->markInProgress($task)) {
            return;
        }

        Auth::setUser($user);
        $context = new RunningTask($task, $user, $tasks);
        $outcome = $registry->resolve($task->type)->handle($context);

        if ($context->cancelled()) {
            $files->delete($outcome->resultPath);

            return;
        }

        if ($outcome->downloadName !== null) {
            $task->parameters = [...$task->parameters, 'download_name' => $outcome->downloadName];
        }

        $tasks->complete($task, $outcome->resultPath, $outcome->summary);
    }

    public function failed(?Throwable $e): void
    {
        $task = BackgroundTask::query()->find($this->taskId);
        if ($task === null || $task->isFinal()) {
            return;
        }

        $timedOut = $e instanceof TimeoutExceededException || $e instanceof MaxAttemptsExceededException;
        if ($e !== null) {
            report($e);
        }

        app(BackgroundTaskServiceInterface::class)->cancel(
            $task,
            $timedOut ? TaskCancelReason::Timeout : TaskCancelReason::Failed,
            $timedOut ? __('tasks.timed_out') : __('tasks.failed'),
        );
    }
}
