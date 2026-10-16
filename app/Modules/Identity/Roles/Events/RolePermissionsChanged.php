<?php

declare(strict_types=1);

namespace App\Modules\Identity\Roles\Events;

use App\Enums\Identity\AuditAction;
use App\Modules\Identity\Audit\Contracts\AuditableEvent;
use App\Modules\Identity\Roles\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A role's permissions (access matrix) changed; caches are already flushed.
 */
final class RolePermissionsChanged implements AuditableEvent
{
    use Dispatchable;

    /**
     * @param  list<string>  $added
     * @param  list<string>  $removed
     */
    public function __construct(
        public readonly Role $role,
        public readonly array $added,
        public readonly array $removed,
    ) {}

    public function auditAction(): AuditAction
    {
        return AuditAction::PermissionChanged;
    }

    public function auditSubject(): Model
    {
        return $this->role;
    }

    public function auditActorId(): ?string
    {
        return null;
    }

    public function auditChanges(): array
    {
        return [['permissions' => $this->removed], ['permissions' => $this->added]];
    }
}
