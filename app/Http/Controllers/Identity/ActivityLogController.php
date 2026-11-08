<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Enums\Identity\TrackedEntity;
use App\Http\Concerns\ApiResponse;
use App\Http\Resources\Identity\ActivityEntryResource;
use App\Interfaces\Identity\ActivityLogServiceInterface;
use App\Models\Identity\AuditLog;
use App\Utilities\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ActivityLogController
{
    use ApiResponse;

    public function __construct(private readonly ActivityLogServiceInterface $activity) {}

    public function index(Request $request, TrackedEntity $entity, string $id): JsonResponse
    {
        $query = ListQuery::fromRequest($request, AuditLog::activityQueryConfig());

        return $this->paginated(ActivityEntryResource::collection($this->activity->forEntity($entity, $id, $query)), $query);
    }
}
