<?php

declare(strict_types=1);

namespace App\Modules\Identity\Tasks\Http\Controllers;

use App\Enums\Identity\TaskCancelReason;
use App\Modules\Identity\Tasks\Contracts\BackgroundTaskServiceInterface;
use App\Modules\Identity\Tasks\Http\Resources\BackgroundTaskResource;
use App\Modules\Identity\Tasks\Models\BackgroundTask;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BackgroundTaskController
{
    use ApiResponse;

    public function __construct(private readonly BackgroundTaskServiceInterface $tasks) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, BackgroundTask::listQueryConfig());

        return $this->paginated(BackgroundTaskResource::collection($this->tasks->list($query)), $query);
    }

    public function show(string $task): JsonResponse
    {
        return $this->ok(new BackgroundTaskResource($this->tasks->find($task)));
    }

    /** Users cancel their own tasks only (administrators see others' but cannot stop them). */
    public function cancel(Request $request, string $task): JsonResponse
    {
        $model = $this->tasks->find($task);
        abort_unless($model->isOwnedBy($request->user() ?? abort(401)), 403);

        return $this->ok(new BackgroundTaskResource($this->tasks->cancel($model, TaskCancelReason::User)));
    }

    public function download(string $task): JsonResponse
    {
        return $this->ok($this->tasks->download($this->tasks->find($task)));
    }
}
