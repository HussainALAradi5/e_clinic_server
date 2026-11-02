<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\RoleName;
use App\Exceptions\Identity\PrivilegeEscalationException;
use App\Models\Identity\Permission;
use App\Models\Identity\User;

/**
 * No privilege escalation: nobody grants a flag, action or role they do not hold themselves.
 * A role counts as held when the caller already has every permission in it; super_admin is only granted by super admins.
 * A null actor (console, seeders) is trusted.
 */
final class PrivilegeGuard
{
    /**
     * @param  iterable<string>  $permissions
     */
    public function assertCanGrantPermissions(?User $actor, iterable $permissions): void
    {
        if ($actor === null || $actor->isSuperAdmin()) {
            return;
        }

        $held = $actor->getAllPermissions()->pluck('name')->all();
        $missing = array_values(array_diff(collect($permissions)->unique()->all(), $held));

        if ($missing !== []) {
            throw new PrivilegeEscalationException(context: ['permissions' => $missing]);
        }
    }

    /**
     * @param  list<string>  $roleNames
     */
    public function assertCanGrantRoles(?User $actor, array $roleNames): void
    {
        if ($actor === null || $actor->isSuperAdmin() || $roleNames === []) {
            return;
        }

        if (in_array(RoleName::SuperAdmin->value, $roleNames, true)) {
            throw new PrivilegeEscalationException(context: ['roles' => [RoleName::SuperAdmin->value]]);
        }

        $this->assertCanGrantPermissions($actor, Permission::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $roleNames))
            ->pluck('name'));
    }
}
