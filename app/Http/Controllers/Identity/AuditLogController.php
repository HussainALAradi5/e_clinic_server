<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Identity\AuditLogIndexRequest;
use App\Http\Resources\Identity\AuditLogResource;
use App\Interfaces\Identity\AuditLogServiceInterface;
use App\Models\Identity\AuditLog;
use App\Utilities\Query\ListQuery;
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

    public function forModel(string $type, string $id): JsonResponse
    {
        return $this->ok(AuditLogResource::collection($this->audit->forModel($type, $id)));
    }
}
