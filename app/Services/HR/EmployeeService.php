<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Dtos\HR\EmployeeData;
use App\Dtos\HR\SalaryComponentData;
use App\Dtos\HR\TerminationData;
use App\Dtos\Identity\CreateUserData;
use App\Enums\HR\EmployeeStatus;
use App\Enums\Identity\UserStatus;
use App\Events\HR\EmployeeHired;
use App\Events\HR\EmployeeTerminated;
use App\Exceptions\HR\AlreadyEmployeeException;
use App\Exceptions\HR\EmployeeTerminatedException;
use App\Exceptions\Shared\InvalidStateTransitionException;
use App\Interfaces\HR\EmployeeServiceInterface;
use App\Interfaces\Identity\RoleServiceInterface;
use App\Interfaces\Identity\UserServiceInterface;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeSalaryComponent;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Services\Identity\PrivilegeGuard;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EmployeeService implements EmployeeServiceInterface
{
    public function __construct(
        private readonly ApplyListQuery $listQuery,
        private readonly UserServiceInterface $users,
        private readonly RoleServiceInterface $roles,
        private readonly PrivilegeGuard $privileges,
        private readonly ClinicClock $clock,
    ) {}

    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->employees(), $query);
    }

    public function find(string $id): Employee
    {
        return $this->employees()
            ->with(['person.nationality', 'person.address.country', 'salaryComponents' => fn ($q) => $q->orderByDesc('effective_from')])
            ->findOrFail($id);
    }

    public function create(EmployeeData $data): Employee
    {
        if ($data->userType === null || $data->userType->isPatient()) {
            throw ValidationException::withMessages(['user_type' => [__('validation.in', ['attribute' => 'account type'])]]);
        }

        $employee = DB::transaction(function () use ($data): Employee {
            $person = $data->personId !== null
                ? Person::query()->findOrFail($data->personId)
                : $this->users->findOrCreatePerson($data->person ?? throw ValidationException::withMessages(['person' => [__('validation.required', ['attribute' => 'person'])]]), $data->forcePerson);

            if (Employee::query()->whereKey($person->id)->exists()) {
                throw new AlreadyEmployeeException;
            }

            $this->attachAccount($person, $data);

            $employee = new Employee($data->values);
            $employee->id = $person->id;
            $employee->save();

            return $employee;
        });

        EmployeeHired::dispatch($employee);

        return $this->find($employee->id);
    }

    public function update(Employee $employee, EmployeeData $data, int $version): Employee
    {
        if ($data->changesSalary()) {
            $this->assertCanManageSalary($employee);
        }

        return DB::transaction(function () use ($employee, $data, $version): Employee {
            $locked = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $locked->assertVersion($version);
            $this->assertNotTerminated($locked);
            $locked->fill($data->values)->save();

            return $this->find($locked->id);
        });
    }

    public function terminate(Employee $employee, TerminationData $data): Employee
    {
        $this->assertNotTerminated($employee);
        if ($data->date->lt($employee->hire_date)) {
            throw ValidationException::withMessages(['termination_date' => [__('validation.after_or_equal', ['attribute' => 'termination date', 'date' => 'hire date'])]]);
        }

        DB::transaction(function () use ($employee, $data): void {
            $employee->forceFill([
                'status' => EmployeeStatus::Terminated,
                'termination_date' => $data->date,
                'termination_reason' => $data->reason,
            ])->save();
            $this->users->changeStatus($employee->user, UserStatus::Inactive, $data->notes ?? $data->reason->label());
        });

        EmployeeTerminated::dispatch($employee);

        return $this->find($employee->id);
    }

    public function changeStatus(Employee $employee, EmployeeStatus $status, string $reason): Employee
    {
        $this->assertNotTerminated($employee);
        if ($status === EmployeeStatus::Terminated || $status === $employee->status) {
            throw new InvalidStateTransitionException(context: ['from' => $employee->status->value, 'to' => $status->value]);
        }

        DB::transaction(function () use ($employee, $status, $reason): void {
            $employee->forceFill(['status' => $status])->save();
            $user = $employee->user;
            // Suspension blocks sign-in; reactivation restores it for staff who have a password.
            if ($status === EmployeeStatus::Suspended && $user->status === UserStatus::Active) {
                $this->users->changeStatus($user, UserStatus::Inactive, $reason);
            } elseif ($status === EmployeeStatus::Active && $user->status === UserStatus::Inactive && $user->password !== null) {
                $this->users->changeStatus($user, UserStatus::Active, $reason);
            }
        });

        return $this->find($employee->id);
    }

    public function addSalaryComponent(Employee $employee, SalaryComponentData $data): EmployeeSalaryComponent
    {
        $this->assertNotTerminated($employee);
        $this->assertCanManageSalary($employee);

        return $employee->salaryComponents()->create([
            'type' => $data->type,
            'name' => $data->name,
            'amount' => $data->amount,
            'effective_from' => $data->effectiveFrom,
            'effective_to' => $data->effectiveTo,
            'is_taxable' => $data->isTaxable,
        ]);
    }

    public function removeSalaryComponent(EmployeeSalaryComponent $component): void
    {
        $this->assertCanManageSalary($component->employee);
        $today = $this->clock->today();

        // Not started yet → never applied, so it can go; otherwise it ends today and stays in payroll history.
        if ($component->effective_from->gt($today)) {
            $component->softDelete();

            return;
        }
        if ($component->effective_to === null || $component->effective_to->gt($today)) {
            $component->forceFill(['effective_to' => $today])->save();
        }
    }

    /**
     * @return Builder<Employee>
     */
    private function employees(): Builder
    {
        $query = Employee::query()
            ->select('employees.*')
            ->join('persons', 'persons.id', '=', 'employees.id')
            ->where('persons.is_deleted', false)
            ->with(['person', 'user.roles', 'department']);

        return OwnershipScope::apply($query, 'employees', Auth::user());
    }

    /** New account (invited unless the employee never signs in) or the person's existing one gets the staff type and roles. */
    private function attachAccount(Person $person, EmployeeData $data): void
    {
        $user = User::query()->find($person->id);
        if ($user === null) {
            $this->users->create($person, new CreateUserData(
                userType: $data->userType ?? throw new \LogicException('user type checked above'),
                roles: $data->roles,
                status: $data->canLogin ? UserStatus::Active : UserStatus::Inactive,
            ));

            return;
        }

        $actor = Auth::user();
        $this->privileges->assertCanGrantRoles($actor instanceof User ? $actor : null, $data->roles);
        $user->forceFill(['user_type' => $data->userType])->save();
        $user->assignRole($data->roles);
        $this->roles->forgetAccess($user);
    }

    private function assertNotTerminated(Employee $employee): void
    {
        if ($employee->isTerminated()) {
            throw new EmployeeTerminatedException;
        }
    }

    /** Salary needs employees.manage_salary, and nobody changes their own (BR-03-EMPL-04 / 05). */
    private function assertCanManageSalary(Employee $employee): void
    {
        $actor = Auth::user();
        if ($actor !== null && (! $actor->can('employees.manage_salary') || $employee->id === $actor->getAuthIdentifier())) {
            throw new AuthorizationException(__('errors.FORBIDDEN'));
        }
    }
}
