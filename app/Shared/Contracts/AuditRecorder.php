<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes one audit entry. Implemented by the Identity AuditLogService; used by the Auditable trait,
 * so the shared kernel never depends on a module.
 */
interface AuditRecorder
{
    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function record(BackedEnum $action, ?Model $model, array $old = [], array $new = [], ?string $module = null): Model;
}
