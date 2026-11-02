<?php

declare(strict_types=1);

namespace App\Modules\HR\Shifts\Services;

use App\Enums\HR\EmployeeStatus;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\HR\Shifts\Contracts\ShiftServiceInterface;
use App\Modules\HR\Shifts\Data\ShiftData;
use App\Modules\HR\Shifts\Exceptions\ShiftHasEmployeesException;
use App\Modules\HR\Shifts\Models\Shift;
use App\Shared\Access\OwnershipScope;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Services\BaseEntityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @extends BaseEntityService<Shift>
 */
final class ShiftService extends BaseEntityService implements ShiftServiceInterface
{
    public function __construct(ApplyListQuery $listQuery)
    {
        parent::__construct($listQuery);
    }

    public function findShift(string $id): Shift
    {
        /** @var Shift $shift */
        $shift = $this->find($id);

        return $shift->load(['employees' => fn ($q) => $q->with(['person', 'department'])
            ->where('employees.status', '!=', EmployeeStatus::Terminated)
            ->join('persons', 'persons.id', '=', 'employees.id')
            ->orderBy('persons.first_name')->orderBy('persons.last_name')
            ->select('employees.*')]);
    }

    public function createShift(ShiftData $data): Shift
    {
        $shift = Shift::query()->create($data->values);

        return $this->findShift($shift->id);
    }

    public function updateShift(Shift $shift, ShiftData $data): Shift
    {
        return DB::transaction(function () use ($shift, $data): Shift {
            $shift->fill($data->values)->save();
            $this->syncHours($shift, $shift->employees()->pluck('id')->map(fn (mixed $id): string => (string) $id)->all());

            return $this->findShift($shift->id);
        });
    }

    public function deleteShift(Shift $shift): void
    {
        $this->delete($shift);
    }

    public function assign(Shift $shift, array $employeeIds): Shift
    {
        $ids = array_values(array_unique($employeeIds));
        $found = Employee::query()->whereIn('id', $ids)->where('status', '!=', EmployeeStatus::Terminated)->pluck('id')->map(fn (mixed $id): string => (string) $id)->all();
        if (count($found) !== count($ids)) {
            throw ValidationException::withMessages(['employee_ids' => [__('errors.SHIFT_UNKNOWN_EMPLOYEE')]]);
        }

        return DB::transaction(function () use ($shift, $found): Shift {
            Employee::query()->whereIn('id', $found)->update(['shift_id' => $shift->id]);
            $this->syncHours($shift, $found);

            return $this->findShift($shift->id);
        });
    }

    public function unassign(Shift $shift, string $employeeId): Shift
    {
        Employee::query()->whereKey($employeeId)->where('shift_id', $shift->id)->update(['shift_id' => null]);

        return $this->findShift($shift->id);
    }

    /**
     * @return Builder<Shift>
     */
    protected function query(): Builder
    {
        return OwnershipScope::apply(Shift::query()->withCount(['employees' => fn ($q) => $q->where('status', '!=', EmployeeStatus::Terminated)]), 'shifts', Auth::user());
    }

    protected function beforeDelete(Model $model): void
    {
        /** @var Shift $model */
        if ($model->employees()->where('status', '!=', EmployeeStatus::Terminated)->exists()) {
            throw new ShiftHasEmployeesException;
        }
    }

    /**
     * The employees' working hours follow their shift (the schedule screens and payroll read them).
     *
     * @param  array<int, string>  $employeeIds
     */
    private function syncHours(Shift $shift, array $employeeIds): void
    {
        if ($employeeIds !== []) {
            Employee::query()->whereIn('id', $employeeIds)->update([
                'work_start_time' => $shift->arriveBy(),
                'work_end_time' => $shift->expectedEnd(),
            ]);
        }
    }
}
