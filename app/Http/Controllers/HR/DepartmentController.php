<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\HR\DepartmentRequest;
use App\Http\Resources\HR\DepartmentResource;
use App\Interfaces\HR\DepartmentServiceInterface;
use App\Models\HR\Department;
use App\Utilities\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class DepartmentController
{
    use ApiResponse;

    public function __construct(private readonly DepartmentServiceInterface $departments) {}

    public function tree(): JsonResponse
    {
        return $this->ok(DepartmentResource::collection($this->departments->tree()));
    }

    public function index(Request $request): JsonResponse
    {
        $query = ListQuery::fromRequest($request, Department::listQueryConfig());

        return $this->paginated(DepartmentResource::collection($this->departments->listDepartments($query)), $query);
    }

    public function show(string $department): JsonResponse
    {
        return $this->ok(new DepartmentResource($this->departments->findDepartment($department)));
    }

    public function store(DepartmentRequest $request): JsonResponse
    {
        return $this->created(new DepartmentResource($this->departments->createDepartment($request->toData())));
    }

    public function update(DepartmentRequest $request, string $department): JsonResponse
    {
        return $this->ok(new DepartmentResource($this->departments->updateDepartment($this->departments->findDepartment($department), $request->toData())));
    }

    public function destroy(string $department): Response
    {
        $this->departments->deleteDepartment($this->departments->findDepartment($department));

        return $this->noContent();
    }

    public function archive(string $department): JsonResponse
    {
        return $this->ok(new DepartmentResource($this->departments->archiveDepartment($this->departments->findDepartment($department))));
    }

    public function unarchive(string $department): JsonResponse
    {
        return $this->ok(new DepartmentResource($this->departments->unarchiveDepartment($this->departments->findDepartment($department))));
    }

    public function restore(string $department): JsonResponse
    {
        return $this->ok(new DepartmentResource($this->departments->restoreDepartment($this->departments->findWithDeletedDepartment($department))));
    }
}
