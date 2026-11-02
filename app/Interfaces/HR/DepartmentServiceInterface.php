<?php

declare(strict_types=1);

namespace App\Interfaces\HR;

use App\Dtos\HR\DepartmentData;
use App\Models\HR\Department;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface DepartmentServiceInterface
{
    /**
     * Root departments with children loaded recursively and employee counts.
     *
     * @return Collection<int, Department>
     */
    public function tree(): Collection;

    /**
     * @return LengthAwarePaginator<int, Department>|CursorPaginator<int, Department>
     */
    public function listDepartments(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** Department with parent, manager, children and employee count. */
    public function findDepartment(string $id): Department;

    public function findWithDeletedDepartment(string $id): Department;

    /** Manager must be an active employee. */
    public function createDepartment(DepartmentData $data): Department;

    /** A parent may not be the department itself or one below it. */
    public function updateDepartment(Department $department, DepartmentData $data): Department;

    /** Soft delete; employees in it or below it → DepartmentHasEmployeesException. */
    public function deleteDepartment(Department $department): void;

    public function archiveDepartment(Department $department): Department;

    public function unarchiveDepartment(Department $department): Department;

    public function restoreDepartment(Department $department): Department;
}
