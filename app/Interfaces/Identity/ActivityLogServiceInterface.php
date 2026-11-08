<?php

declare(strict_types=1);

namespace App\Interfaces\Identity;

use App\Enums\Identity\TrackedEntity;
use App\Models\Identity\AuditLog;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ActivityLogServiceInterface
{
    /**
     * The event log of one record, newest first. Read-only: entries are written by the audit trail.
     *
     * @return LengthAwarePaginator<int, AuditLog>|CursorPaginator<int, AuditLog>
     */
    public function forEntity(TrackedEntity $entity, string $id, ListQuery $query): LengthAwarePaginator|CursorPaginator;
}
