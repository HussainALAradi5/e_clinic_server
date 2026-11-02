<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Dtos\Identity\AccessRowData;
use App\Dtos\Identity\EffectiveAccessData;
use App\Dtos\Identity\RoleData;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserStatus;
use App\Events\Identity\RolePermissionsChanged;
use App\Events\Identity\UserRolesChanged;
use App\Exceptions\Identity\CannotModifySelfException;
use App\Exceptions\Identity\LastSuperAdminException;
use App\Exceptions\Identity\RoleInUseException;
use App\Exceptions\Identity\SystemRoleProtectedException;
use App\Interfaces\Identity\RoleServiceInterface;
use App\Models\Identity\Permission;
use App\Models\Identity\Role;
use App\Models\Identity\User;
use App\Services\Base\BaseEntityService;
use App\Utilities\Access\PermissionExpression;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\CacheKeys;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @extends BaseEntityService<Role>
 */
final class RoleService extends BaseEntityService implements RoleServiceInterface
{
    public function __construct(ApplyListQuery $listQuery, private readonly PrivilegeGuard $privileges)
    {
        parent::__construct($listQuery);
    }

    protected function query(): Builder
    {
        return Role::query()->withCount(['users', 'permissions']);
    }

    public function listRoles(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->list($query);
    }

    public function findRole(string $id): Role
    {
        return $this->find($id)->load('permissions');
    }

    public function createRole(RoleData $data): Role
    {
        $permissions = $data->permissions ?? [];
        $this->privileges->assertCanGrantPermissions($this->actor(), $permissions);

        $role = DB::transaction(function () use ($data, $permissions): Role {
            /** @var Role $role */
            $role = Role::query()->create([
                'name' => $data->name,
                'guard_name' => self::guard(),
                'display_name' => $data->displayName,
                'display_name_ar' => $data->displayNameAr,
                'description' => $data->description,
                'is_system' => false,
            ]);
            $role->syncPermissions($this->permissionModels($permissions));

            return $role;
        });

        if ($permissions !== []) {
            RolePermissionsChanged::dispatch($role, $permissions, []);
        }

        return $this->findRole($role->id);
    }

    public function updateRole(Role $role, RoleData $data): Role
    {
        $renamed = $data->name !== null && $data->name !== $role->name;
        if ($role->is_system && ($renamed || $data->permissions !== null)) {
            throw new SystemRoleProtectedException;
        }

        $current = $role->permissions->pluck('name')->all();
        $added = $data->permissions === null ? [] : array_values(array_diff($data->permissions, $current));
        $removed = $data->permissions === null ? [] : array_values(array_diff($current, $data->permissions));
        $this->privileges->assertCanGrantPermissions($this->actor(), $added);

        DB::transaction(function () use ($role, $data): void {
            $role->fill(array_filter([
                'name' => $data->name,
                'display_name' => $data->displayName,
                'display_name_ar' => $data->displayNameAr,
                'description' => $data->description,
            ], fn (?string $value): bool => $value !== null))->save();

            if ($data->permissions !== null) {
                $role->syncPermissions($this->permissionModels($data->permissions));
            }
        });

        if ($added !== [] || $removed !== []) {
            $this->afterPermissionsChanged($role, $added, $removed);
        }

        return $this->findRole($role->id);
    }

    public function deleteRole(Role $role): void
    {
        $this->delete($role);
    }

    public function archiveRole(Role $role): Role
    {
        return $role->isArchived() ? $role : $this->archive($role);
    }

    public function unarchiveRole(Role $role): Role
    {
        return $role->isArchived() ? $this->unarchive($role) : $role;
    }

    public function restoreRole(Role $role): Role
    {
        return $role->isDeleted() ? $this->restore($role) : $role;
    }

    protected function beforeDelete(Model $model): void
    {
        if ($model->is_system) {
            throw new SystemRoleProtectedException;
        }
        if ($model->users()->exists()) {
            throw new RoleInUseException(context: ['users_count' => $model->users()->count()]);
        }
    }

    public function listPermissions(): Collection
    {
        return Permission::query()->orderBy('module')->orderBy('name')->get()
            ->groupBy('module')
            ->map(fn (Collection $permissions, string $module): array => [
                'module' => $module,
                'permissions' => array_values($permissions->map(fn (Permission $p): array => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'description' => $p->description,
                ])->all()),
            ])
            ->values();
    }

    public function listResources(): Collection
    {
        return collect(self::resources())->map(fn (array $config, string $resource): array => [
            'resource' => $resource,
            'module' => (string) $config['module'],
            'flags' => Permission::flags(),
            'actions' => $config['actions'],
            'ownership' => (string) $config['ownership'],
        ])->values();
    }

    public function getAccessMatrix(Role $role): Collection
    {
        $granted = array_flip($role->permissions()->pluck('name')->all());
        $has = fn (string $name): bool => isset($granted[$name]);

        return collect(self::resources())->map(fn (array $config, string $resource): AccessRowData => new AccessRowData(
            resource: $resource,
            module: (string) $config['module'],
            readOwn: $has("{$resource}.read_own"),
            readAll: $has("{$resource}.read_all"),
            create: $has("{$resource}.create"),
            write: $has("{$resource}.write"),
            delete: $has("{$resource}.delete"),
            actions: array_values(array_filter($config['actions'], fn (string $action): bool => $has("{$resource}.{$action}"))),
            availableActions: $config['actions'],
        ))->values();
    }

    public function updateAccessMatrix(Role $role, array $rows): Collection
    {
        if ($role->isSuperAdmin()) {
            throw new SystemRoleProtectedException;
        }
        $this->validateRows($rows);

        $current = $role->permissions()->pluck('name')->all();
        $resources = array_map(fn (AccessRowData $row): string => $row->resource, $rows);
        $kept = array_filter($current, fn (string $name): bool => ! in_array(explode('.', $name, 2)[0], $resources, true));
        $target = array_values(array_unique([...$kept, ...array_merge(...array_map(fn (AccessRowData $r): array => $r->permissions(), $rows))]));

        $added = array_values(array_diff($target, $current));
        $removed = array_values(array_diff($current, $target));
        $this->privileges->assertCanGrantPermissions($this->actor(), $added);

        if ($added !== [] || $removed !== []) {
            DB::transaction(fn () => $role->syncPermissions($this->permissionModels($target)));
            $this->afterPermissionsChanged($role, $added, $removed, revokeTokens: true);
        }

        return $this->getAccessMatrix($role->refresh());
    }

    public function effectiveAccess(User $user): EffectiveAccessData
    {
        /** @var array{roles: list<string>, permissions: list<string>} $data */
        $data = Cache::remember(
            CacheKeys::permissions($user->id),
            now()->addMinutes(Config::integer('clinic.auth.access_cache_minutes')),
            fn (): array => [
                'roles' => $user->getRoleNames()->values()->all(),
                'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
            ],
        );

        return EffectiveAccessData::fromArray($data);
    }

    public function evaluate(User $user, string $expression, ?Model $record = null): bool
    {
        $parsed = PermissionExpression::parse($expression);
        $names = array_diff($parsed->permissions(), [PermissionExpression::AUTHENTICATED]);
        $unknown = array_diff($names, Permission::query()->whereIn('name', $names)->pluck('name')->all());

        if ($unknown !== []) {
            Log::warning('Permission expression references unknown permissions.', ['expression' => $expression, 'unknown' => array_values($unknown)]);
        }

        return $parsed->evaluate($user, $record);
    }

    public function syncUserRoles(User $user, array $roleNames): User
    {
        $actor = $this->actor();
        if ($user->is($actor)) {
            throw new CannotModifySelfException;
        }

        $current = array_values(array_map('strval', $user->getRoleNames()->all()));
        $added = array_values(array_diff($roleNames, $current));
        $this->privileges->assertCanGrantRoles($actor, $added);

        $losesSuperAdmin = in_array(RoleName::SuperAdmin->value, $current, true) && ! in_array(RoleName::SuperAdmin->value, $roleNames, true);
        if ($losesSuperAdmin && $user->status === UserStatus::Active && User::activeSuperAdminCount() <= 1) {
            throw new LastSuperAdminException;
        }

        DB::transaction(function () use ($user, $roleNames): void {
            $user->syncRoles($roleNames);
            $user->tokens()->delete();
        });
        $this->forgetAccess($user);

        UserRolesChanged::dispatch($user, $current, $roleNames);

        return $user->refresh()->load('person', 'roles');
    }

    public function addMembers(Role $role, array $userIds): int
    {
        if ($role->isArchived()) {
            throw ValidationException::withMessages(['user_ids' => 'This role is archived. Unarchive it before adding people.']);
        }
        if (in_array($this->actor()?->getKey(), $userIds, true)) {
            throw new CannotModifySelfException;
        }
        $this->privileges->assertCanGrantRoles($this->actor(), [$role->name]);

        return DB::transaction(function () use ($role, $userIds): int {
            $added = 0;
            User::query()->whereKey($userIds)->with('roles')->get()->each(function (User $user) use ($role, &$added): void {
                $current = array_values(array_map('strval', $user->getRoleNames()->all()));
                if (! in_array($role->name, $current, true)) {
                    $this->syncUserRoles($user, [...$current, $role->name]);
                    $added++;
                }
            });

            return $added;
        });
    }

    public function removeMember(Role $role, User $user): User
    {
        $current = array_values(array_map('strval', $user->getRoleNames()->all()));
        if (! in_array($role->name, $current, true)) {
            return $user->load('person', 'roles');
        }
        $remaining = array_values(array_diff($current, [$role->name]));
        if ($remaining === []) {
            throw ValidationException::withMessages(['user' => 'This is their only role. Give them another role first, or deactivate the account.']);
        }

        return $this->syncUserRoles($user, $remaining);
    }

    public function forgetAccess(User $user): void
    {
        Cache::forget(CacheKeys::permissions($user->id));
    }

    /**
     * Flush caches of the role's users and (matrix changes) revoke their tokens — except the caller's current one.
     *
     * @param  list<string>  $added
     * @param  list<string>  $removed
     */
    private function afterPermissionsChanged(Role $role, array $added, array $removed, bool $revokeTokens = false): void
    {
        $currentToken = $this->actor()?->currentAccessToken();
        $keep = $currentToken instanceof PersonalAccessToken ? $currentToken->getKey() : null;

        User::role($role->name)->each(function (User $user) use ($revokeTokens, $keep): void {
            $this->forgetAccess($user);
            if ($revokeTokens) {
                $user->tokens()->when($keep !== null, fn (Builder $q) => $q->whereKeyNot($keep))->delete();
            }
        });

        RolePermissionsChanged::dispatch($role, $added, $removed);
    }

    /**
     * @param  list<AccessRowData>  $rows
     */
    private function validateRows(array $rows): void
    {
        $resources = self::resources();
        $errors = [];

        foreach ($rows as $i => $row) {
            if (($row->write || $row->delete || $row->create) && ! $row->readOwn && ! $row->readAll) {
                $errors["rows.{$i}.read_own"] = [__('validation.required_if_accepted', ['attribute' => 'read', 'other' => 'write / delete'])];
            }
            $unknown = array_diff($row->actions, $resources[$row->resource]['actions'] ?? []);
            if ($unknown !== []) {
                $errors["rows.{$i}.actions"] = [__('validation.in', ['attribute' => 'actions']).' ('.implode(', ', $unknown).')'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  list<string>  $names
     * @return Collection<int, Permission>
     */
    private function permissionModels(array $names): Collection
    {
        $models = Permission::query()->where('guard_name', self::guard())->whereIn('name', $names)->get();
        $missing = array_diff($names, $models->pluck('name')->all());

        if ($missing !== []) {
            throw ValidationException::withMessages(['permissions' => [__('validation.exists', ['attribute' => implode(', ', $missing)])]]);
        }

        return $models->toBase();
    }

    /**
     * @return array<string, array{module: string, actions: list<string>, ownership: string}>
     */
    private static function resources(): array
    {
        /** @var array<string, array{module: string, actions: list<string>, ownership: string}> */
        return Config::array('access.resources');
    }

    private static function guard(): string
    {
        return Config::string('access.guard');
    }

    private function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
