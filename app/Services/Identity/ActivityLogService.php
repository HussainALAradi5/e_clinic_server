<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\TrackedEntity;
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

    /** A log shows the record's changes, so opening one also needs the right to read that kind of record. */
    private function assertCanRead(TrackedEntity $entity): void
    {
        $resource = $entity->resource();

        if ($resource !== null && ! PermissionExpression::parse("{$resource}.read_all")->evaluate(Auth::user())) {
            throw new ActivityEntityForbiddenException;
        }
    }
}
