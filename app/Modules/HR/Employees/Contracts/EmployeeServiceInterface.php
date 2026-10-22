<?php

declare(strict_types=1);

namespace App\Modules\HR\Employees\Contracts;

use App\Enums\HR\EmployeeStatus;
use App\Modules\HR\Employees\Data\EmployeeData;
use App\Modules\HR\Employees\Data\SalaryComponentData;
use App\Modules\HR\Employees\Data\TerminationData;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\HR\Employees\Models\EmployeeSalaryComponent;
use App\Shared\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EmployeeServiceInterface
{
    /**
     * Search by name / person code (joined from persons), department, status, contract type.
     *
     * @return LengthAwarePaginator<int, Employee>|CursorPaginator<int, Employee>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** Employee with person, account, department and salary components. */
    public function find(string $id): Employee;

    /** Hires: person (reused or created) → user → employee, all with the same id, in one transaction. */
    public function create(EmployeeData $data): Employee;

    /** Employment data (salary data needs employees.manage_salary; terminated employees are read-only). */
    public function update(Employee $employee, EmployeeData $data, int $version): Employee;

    /** Ends employment and deactivates the account. */
    public function terminate(Employee $employee, TerminationData $data): Employee;

    /** Suspend (account deactivated) or reactivate. */
    public function changeStatus(Employee $employee, EmployeeStatus $status, string $reason): Employee;

    public function addSalaryComponent(Employee $employee, SalaryComponentData $data): EmployeeSalaryComponent;

    /** Ends the component (effective_to = today) — kept for payroll history. */
    public function removeSalaryComponent(EmployeeSalaryComponent $component): void;
}
