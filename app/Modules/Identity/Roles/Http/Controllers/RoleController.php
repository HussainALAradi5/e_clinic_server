<?php

declare(strict_types=1);

namespace App\Modules\Identity\Roles\Http\Controllers;

use App\Modules\Identity\Roles\Contracts\RoleServiceInterface;
use App\Modules\Identity\Roles\Data\AccessRowData;
use App\Modules\Identity\Roles\Http\Requests\StoreRoleRequest;
use App\Modules\Identity\Roles\Http\Requests\SyncUserRolesRequest;
use App\Modules\Identity\Roles\Http\Requests\UpdateAccessMatrixRequest;
use App\Modules\Identity\Roles\Http\Requests\UpdateRoleRequest;
use App\Modules\Identity\Roles\Http\Resources\RoleResource;
use App\Modules\Identity\Roles\Models\Role;
use App\Modules\Identity\Users\Contracts\UserServiceInterface;
use App\Modules\Identity\Users\Http\Resources\UserResource;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
use App\Shared\Support\GenericEntityScopes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

final class RoleController
{
    use ApiResponse;

    public function __construct(private readonly RoleServiceInterface $roles) {}

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Role::listQueryConfig());

        return $this->paginated(RoleResource::collection($this->roles->listRoles($query)), $query);
    }

    public function show(string $role): JsonResponse
    {
        return $this->ok(new RoleResource($this->roles->findRole($role)));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        return $this->created(new RoleResource($this->roles->createRole($request->toData())));
    }

    public function update(UpdateRoleRequest $request, string $role): JsonResponse
    {
        return $this->ok(new RoleResource($this->roles->updateRole($this->roles->findRole($role), $request->toData())));
    }

    public function destroy(string $role): Response
    {
        $this->roles->deleteRole($this->roles->findRole($role));

        return $this->noContent();
    }

    public function archiveRole(string $role): JsonResponse
    {
        return $this->ok(new RoleResource($this->roles->archiveRole($this->roles->findRole($role))));
    }

    public function unarchiveRole(string $role): JsonResponse
    {
        return $this->ok(new RoleResource($this->roles->unarchiveRole($this->roles->findRole($role))));
    }

    public function restoreRole(string $role): JsonResponse
    {
        $model = GenericEntityScopes::withDeleted(Role::query())->findOrFail($role);

        return $this->ok(new RoleResource($this->roles->restoreRole($model)));
    }

    public function permissions(): JsonResponse
    {
        return $this->ok($this->roles->listPermissions());
    }

    public function resources(): JsonResponse
    {
        return $this->ok($this->roles->listResources());
    }

    public function access(string $role): JsonResponse
    {
        return $this->ok(self::rows($this->roles->getAccessMatrix($this->roles->findRole($role))));
    }

    public function updateAccess(UpdateAccessMatrixRequest $request, string $role): JsonResponse
    {
        return $this->ok(self::rows($this->roles->updateAccessMatrix($this->roles->findRole($role), $request->rows())));
    }

    public function myAccess(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->ok($this->roles->effectiveAccess($user)->toArray());
    }

    public function syncUserRoles(SyncUserRolesRequest $request, string $user, UserServiceInterface $users): JsonResponse
    {
        return $this->ok((new UserResource($this->roles->syncUserRoles($users->find($user), $request->roleNames())))->withPerson());
    }

    /**
     * @param  Collection<int, AccessRowData>  $rows
     * @return list<array<string, mixed>>
     */
    private static function rows(Collection $rows): array
    {
        return array_values($rows->map(fn (AccessRowData $row): array => $row->toArray())->all());
    }
}
