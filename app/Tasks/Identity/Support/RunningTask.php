<?php

declare(strict_types=1);

namespace App\Tasks\Identity\Support;

use App\Enums\Shared\GenericStatus;
use App\Models\Identity\User;
use App\Tasks\Identity\Contracts\BackgroundTaskServiceInterface;
use App\Tasks\Identity\Models\BackgroundTask;
use App\Tasks\Shared\TaskContext;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * TaskContext of a task being run by RunBackgroundTask.
 */
final class RunningTask implements TaskContext
{
    public function __construct(
        private readonly BackgroundTask $task,
        private readonly User $user,
        private readonly BackgroundTaskServiceInterface $service,
    ) {}

    public function id(): string
    {
        return $this->task->id;
    }

    public function parameters(): array
    {
        return $this->task->parameters;
    }

    public function user(): Authenticatable
    {
        return $this->user;
    }

    public function progress(int $percent): void
    {
        $this->service->reportProgress($this->task, $percent);
    }

    public function cancelled(): bool
    {
        return BackgroundTask::query()->whereKey($this->task->id)->value('status') === GenericStatus::Cancelled->value;
    }
}
