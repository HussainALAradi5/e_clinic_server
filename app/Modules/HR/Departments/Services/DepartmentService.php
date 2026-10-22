<?php

declare(strict_types=1);

namespace App\Modules\HR\Departments\Services;

use App\Enums\HR\EmployeeStatus;
use App\Modules\HR\Departments\Contracts\DepartmentServiceInterface;
use App\Modules\HR\Departments\Data\DepartmentData;
use App\Modules\HR\Departments\Exceptions\DepartmentCycleException;
use App\Modules\HR\Departments\Exceptions\DepartmentHasEmployeesException;
use App\Modules\HR\Departments\Models\Department;
use App\Modules\HR\Employees\Models\Employee;
use App\Shared\Access\OwnershipScope;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Query\ListQuery;
use App\Shared\Services\BaseEntityService;
use App\Shared\Support\GenericEntityScopes;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @extends BaseEntityService<Department>
 */
final class DepartmentService extends BaseEntityService implements DepartmentServiceInterface
{
    public function __construct(ApplyListQuery $listQuery)
    {
        parent::__construct($listQuery);
    }

    public function tree(): Collection
    {
        $departments = Department::query()->notArchived()->withCount('employees')->with('manager.user.person')->orderBy('name')->get();
        $byParent = $departments->groupBy(fn (Department $d): string => $d->parent_id ?? '');
        $attach = function (Department $department) use (&$attach, $byParent): Department {
            return $department->setRelation('children', ($byParent[$department->id] ?? collect())->map($attach)->values());
        };
        $ids = $departments->pluck('id')->flip();

        return $departments->filter(fn (Department $d): bool => $d->parent_id === null || ! $ids->has($d->parent_id))->map($attach)->values();
    }

    public function listDepartments(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->list($query);
    }

    public function findDepartment(string $id): Department
    {
        return $this->find($id)->load(['children' => fn ($q) => $q->withCount('employees')->orderBy('name')]);
    }

    public function findWithDeletedDepartment(string $id): Department
    {
        return $this->findWithDeleted($id);
    }

    public function createDepartment(DepartmentData $data): Department
    {
        return DB::transaction(function () use ($data): Department {
            $this->assertCodeFree((string) $data->values['code']);
            $department = new Department($data->values);
            $this->assertManager($department->manager_id);
            $department->save();

            return $this->findDepartment($department->id);
        });
    }

    public function updateDepartment(Department $department, DepartmentData $data): Department
    {
        return DB::transaction(function () use ($department, $data): Department {
            $department->fill($data->values);
            if ($department->isDirty('code')) {
                $this->assertCodeFree($department->code, $department->id);
            }
            if ($department->isDirty('manager_id')) {
                $this->assertManager($department->manager_id);
            }
            if ($department->isDirty('parent_id') && $department->parent_id !== null
                && in_array($department->parent_id, [$department->id, ...$this->descendantIds($department)], true)) {
                throw new DepartmentCycleException(context: ['errors' => ['parent_id' => [__('errors.DEPARTMENT_CYCLE')]]]);
            }
            $department->save();

            return $this->findDepartment($department->id);
        });
    }

    public function deleteDepartment(Department $department): void
    {
        $this->delete($department);
    }

    public function archiveDepartment(Department $department): Department
    {
        return $department->isArchived() ? $department : $this->archive($department);
    }

    public function unarchiveDepartment(Department $department): Department
    {
        return $department->isArchived() ? $this->unarchive($department) : $department;
    }

    public function restoreDepartment(Department $department): Department
    {
        return $department->isDeleted() ? $this->restore($department) : $department;
    }

    public function restore(Model $model): Model
    {
        /** @var Department $model */
        $this->assertCodeFree($model->code, $model->id);

        return parent::restore($model);
    }

    /**
     * @return Builder<Department>
     */
    protected function query(): Builder
    {
        return OwnershipScope::apply(Department::query()->with(['parent', 'manager.user.person'])->withCount('employees'), 'departments', Auth::user());
    }

    protected function beforeDelete(Model $model): void
    {
        /** @var Department $model */
        $ids = [$model->id, ...$this->descendantIds($model)];
        if (Employee::query()->whereIn('department_id', $ids)->where('status', '!=', EmployeeStatus::Terminated)->exists()) {
            throw new DepartmentHasEmployeesException;
        }
    }

    private function assertCodeFree(string $code, ?string $exceptId = null): void
    {
        $taken = GenericEntityScopes::withDeleted(Department::query())->where('code', $code)
            ->when($exceptId !== null, fn (Builder $q) => $q->whereKeyNot($exceptId))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['code' => [__('validation.unique', ['attribute' => 'code'])]]);
        }
    }

    private function assertManager(?string $employeeId): void
    {
        if ($employeeId !== null && ! Employee::query()->whereKey($employeeId)->where('status', EmployeeStatus::Active)->exists()) {
            throw ValidationException::withMessages(['manager_id' => [__('validation.exists', ['attribute' => 'manager'])]]);
        }
    }

    /** @return list<string> */
    private function descendantIds(Department $department): array
    {
        $ids = [];
        $frontier = [$department->id];
        while ($frontier !== []) {
            $frontier = Department::query()->withDeleted()->whereIn('parent_id', $frontier)->pluck('id')->map(fn (mixed $id): string => (string) $id)->all();
            $ids = [...$ids, ...$frontier];
        }

        return array_values($ids);
    }
}
