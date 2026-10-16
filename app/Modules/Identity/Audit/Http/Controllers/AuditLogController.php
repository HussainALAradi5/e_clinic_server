<?php

declare(strict_types=1);

namespace App\Modules\Identity\Audit\Http\Controllers;

use App\Modules\Identity\Audit\Contracts\AuditLogServiceInterface;
use App\Modules\Identity\Audit\Http\Requests\AuditLogIndexRequest;
use App\Modules\Identity\Audit\Http\Resources\AuditLogResource;
use App\Modules\Identity\Audit\Models\AuditLog;
use App\Modules\Identity\Tasks\Http\Resources\BackgroundTaskResource;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
use Illuminate\Http\JsonResponse;

final class AuditLogController
{
    use ApiResponse;

    public function __construct(private readonly AuditLogServiceInterface $audit) {}

    public function index(AuditLogIndexRequest $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, AuditLog::listQueryConfig());

        return $this->paginated(AuditLogResource::collection($this->audit->list($query)), $query);
    }

    /** 202 + background task `audit_export` (CSV of the same filtered list). */
    public function export(AuditLogIndexRequest $request): JsonResponse
    {
        $params = $request->query();
        unset($params['cursor'], $params['page'], $params['per_page']);
        $task = $this->audit->export($params);

        return $this->accepted(__('messages.task_queued'), ['task_id' => $task->id], new BackgroundTaskResource($task));
    }

    public function forModel(string $type, string $id): JsonResponse
    {
        return $this->ok(AuditLogResource::collection($this->audit->forModel($type, $id)));
    }
}
