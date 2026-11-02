<?php

declare(strict_types=1);

namespace App\Models\Base;

use App\Interfaces\Shared\Auditable;
use App\Interfaces\Shared\GenericEntity;
use App\Traits\Shared\Auditable as AuditsChanges;
use App\Traits\Shared\Blameable;
use App\Traits\Shared\HasCode;
use App\Traits\Shared\HasGenericEntity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Base of every business table (GenericEntity): UUID v7 id, code, timestamps, is_archived, is_deleted, blame columns;
 * every change is written to audit_logs.
 * Physical deletes are forbidden — delete() soft deletes.
 *
 * Migration: `$table->genericEntity(); $table->blameable();`
 */
abstract class BaseModel extends Model implements Auditable, GenericEntity
{
    use AuditsChanges;
    use Blameable;
    use HasCode;
    use HasGenericEntity;
    use HasUuids;

    /** @var list<string> */
    protected $guarded = ['id', 'is_archived', 'is_deleted', 'deleted_at', 'deleted_by', 'created_by', 'updated_by'];

    public function delete(): bool
    {
        $this->softDelete();

        return true;
    }

    public function forceDelete(): never
    {
        throw new LogicException('Physical deletes are not allowed (soft delete only).');
    }
}
