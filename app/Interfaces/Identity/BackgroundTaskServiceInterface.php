<?php

declare(strict_types=1);

namespace App\Interfaces\Identity;

use App\Enums\Identity\TaskCancelReason;
use App\Enums\Identity\TaskType;
use App\Interfaces\Shared\TaskDispatcher;
use App\Models\Identity\BackgroundTask;
use App\Models\Identity\User;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

interface BackgroundTaskServiceInterface extends TaskDispatcher
{
    /**
     * **Internal API** — creates a pending task and queues its job after commit.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function dispatch(TaskType $type, array $parameters, User $user, ?Model $subject = null): BackgroundTask;

    /** **Internal** — pending → in_progress; false when the task was cancelled meanwhile (the job stops). */
    public function markInProgress(BackgroundTask $task): bool;

    /** **Internal** — updates progress, at most one write every 2 seconds. */
    public function reportProgress(BackgroundTask $task, int $percent): void;

    /**
     * **Internal** — in_progress → completed with the result file / summary; notifies the owner.
     *
     * @param  array<string, mixed>  $summary
     */
    public function complete(BackgroundTask $task, ?string $resultPath = null, array $summary = []): void;

    /** pending | in_progress → cancelled (user, failed or timeout). */
    public function cancel(BackgroundTask $task, TaskCancelReason $reason, ?string $error = null): BackgroundTask;

    /**
     * The current user's tasks (administrators: everyone's).
     *
     * @return LengthAwarePaginator<int, BackgroundTask>|CursorPaginator<int, BackgroundTask>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    public function find(string $id): BackgroundTask;

    /**
     * 5-minute signed link to the result file (download is audited).
     *
     * @return array{url: string, expires_at: string}
     */
    public function download(BackgroundTask $task): array;
}
