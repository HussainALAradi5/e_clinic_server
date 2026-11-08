<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\TrackedEntity;
use App\Enums\Identity\UserType;
use App\Exceptions\Identity\ActivityEntityForbiddenException;
use App\Interfaces\Identity\ActivityLogServiceInterface;
use App\Models\Identity\AuditLog;
use App\Utilities\Access\PermissionExpression;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

/** Who changed what on a record, from the append-only audit trail. */
final class ActivityLogService implements ActivityLogServiceInterface
{
    public function __construct(private readonly ApplyListQuery $listQuery) {}

    public function forEntity(TrackedEntity $entity, string $id, ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        $this->assertCanRead($entity);

        $builder = AuditLog::query()->with('user.person')
            ->whereIn('auditable_type', $entity->auditableTypes())
            ->where('auditable_id', $id);

        return $this->listQuery->paginate($builder, $query);
    }

    private function assertCanRead(TrackedEntity $entity): void
    {
        $user = Auth::user();

        if ($user?->user_type === UserType::Patient || ! PermissionExpression::parse($entity->readRight())->evaluate($user)) {
            throw new ActivityEntityForbiddenException;
        }
    }
}
