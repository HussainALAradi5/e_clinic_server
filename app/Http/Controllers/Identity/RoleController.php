<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Dtos\Identity\AccessRowData;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Identity\AddRoleMembersRequest;
use App\Http\Requests\Identity\StoreRoleRequest;
use App\Http\Requests\Identity\SyncUserRolesRequest;
use App\Http\Requests\Identity\UpdateAccessMatrixRequest;
use App\Http\Requests\Identity\UpdateRoleRequest;
use App\Http\Resources\Identity\RoleResource;
use App\Http\Resources\Identity\UserResource;
use App\Interfaces\Identity\RoleServiceInterface;
use App\Interfaces\Identity\UserServiceInterface;
use App\Models\Identity\Role;
use App\Models\Identity\User;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\GenericEntityScopes;
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

    public function addMembers(AddRoleMembersRequest $request, string $role): JsonResponse
    {
        return $this->ok(['added' => $this->roles->addMembers($this->roles->findRole($role), $request->userIds())]);
    }

    public function removeMember(string $role, string $user, UserServiceInterface $users): JsonResponse
    {
        return $this->ok((new UserResource($this->roles->removeMember($this->roles->findRole($role), $users->find($user))))->withPerson());
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
