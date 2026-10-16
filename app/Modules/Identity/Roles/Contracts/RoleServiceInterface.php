<?php

declare(strict_types=1);

namespace App\Modules\Identity\Roles\Contracts;

use App\Modules\Identity\Roles\Data\AccessRowData;
use App\Modules\Identity\Roles\Data\EffectiveAccessData;
use App\Modules\Identity\Roles\Data\RoleData;
use App\Modules\Identity\Roles\Models\Role;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface RoleServiceInterface
{
    /**
     * Roles with permission count and user count (generic list query).
     *
     * @return LengthAwarePaginator<int, Role>|CursorPaginator<int, Role>
     */
    public function listRoles(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** Role with its permissions. */
    public function findRole(string $id): Role;

    /** Creates a custom role. */
    public function createRole(RoleData $data): Role;

    /** Updates labels / description and (custom roles only) name and permissions. */
    public function updateRole(Role $role, RoleData $data): Role;

    /** Soft-deletes a custom role that no user holds. */
    public function deleteRole(Role $role): void;

    public function archiveRole(Role $role): Role;

    public function unarchiveRole(Role $role): Role;

    public function restoreRole(Role $role): Role;

    /**
     * All permissions grouped by module (role editor).
     *
     * @return Collection<int, array{module: string, permissions: list<array{id: string, name: string, description: ?string}>}>
     */
    public function listPermissions(): Collection;

    /**
     * Protected resources with their flags, special actions and ownership rule (config/access.php).
     *
     * @return Collection<int, array{resource: string, module: string, flags: list<string>, actions: list<string>, ownership: string}>
     */
    public function listResources(): Collection;

    /**
     * Odoo-style matrix: one row per resource with read_own, read_all, create, write, delete + granted special actions.
     *
     * @return Collection<int, AccessRowData>
     */
    public function getAccessMatrix(Role $role): Collection;

    /**
     * Saves the given rows (other resources keep their rights) and syncs the mirrored permissions.
     *
     * @param  list<AccessRowData>  $rows
     * @return Collection<int, AccessRowData>
     */
    public function updateAccessMatrix(Role $role, array $rows): Collection;

    /** Merged (OR across roles) roles + permissions, cached 10 minutes. */
    public function effectiveAccess(User $user): EffectiveAccessData;

    /** Evaluates a permission expression (`&`, `|`, `!`, parentheses); with a record, own-flags also check ownership. */
    public function evaluate(User $user, string $expression, ?Model $record = null): bool;

    /**
     * Replaces a user's roles.
     *
     * @param  list<string>  $roleNames
     */
    public function syncUserRoles(User $user, array $roleNames): User;

    /** Drops the cached effective access of a user. */
    public function forgetAccess(User $user): void;
}
