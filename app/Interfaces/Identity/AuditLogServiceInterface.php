<?php

declare(strict_types=1);

namespace App\Interfaces\Identity;

use App\Interfaces\Shared\AuditRecorder;
use App\Models\Identity\AuditLog;
use App\Utilities\Query\ListQuery;
use BackedEnum;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface AuditLogServiceInterface extends AuditRecorder
{
    /**
     * **Internal** (Auditable trait, services, event listener) — writes one audit entry (queued, after commit).
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function record(BackedEnum $action, ?Model $model, array $old = [], array $new = [], ?string $module = null, ?string $userId = null): AuditLog;

    /**
     * Cursor-paginated search by user, module, action, record and date range (max 93 days).
     *
     * @return LengthAwarePaginator<int, AuditLog>|CursorPaginator<int, AuditLog>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /**
     * History of one record (`type` is a whitelisted alias, e.g. `invoice`), newest first.
     *
     * @return Collection<int, AuditLog>
     */
    public function forModel(string $type, string $id): Collection;
}
