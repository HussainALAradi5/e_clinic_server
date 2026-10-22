<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Doctors\Services;

use App\Enums\HR\EmployeeStatus;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Modules\Appointments\Doctors\Contracts\DoctorServiceInterface;
use App\Modules\Appointments\Doctors\Data\DoctorData;
use App\Modules\Appointments\Doctors\Exceptions\EmployeeNotEligibleAsDoctorException;
use App\Modules\Appointments\Doctors\Models\Doctor;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\Identity\Roles\Contracts\RoleServiceInterface;
use App\Modules\Identity\Roles\Services\PrivilegeGuard;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Access\OwnershipScope;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class DoctorService implements DoctorServiceInterface
{
    public const RELATIONS = ['person', 'employee.department', 'specialty', 'consultationService', 'followUpService', 'defaultRoom'];

    public function __construct(
        private readonly ApplyListQuery $listQuery,
        private readonly PrivilegeGuard $privileges,
        private readonly RoleServiceInterface $roles,
    ) {}

    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->doctors(), $query);
    }

    public function find(string $id): Doctor
    {
        return $this->doctors()->findOrFail($id);
    }

    public function create(DoctorData $data): Doctor
    {
        $employee = Employee::query()->with('user')->find($data->employeeId);
        if ($employee === null || $employee->status !== EmployeeStatus::Active || Doctor::query()->whereKey($employee->id)->exists()) {
            throw new EmployeeNotEligibleAsDoctorException;
        }

        $actor = Auth::user();
        $this->privileges->assertCanGrantRoles($actor instanceof User ? $actor : null, [RoleName::Doctor->value]);

        DB::transaction(function () use ($employee, $data): void {
            Doctor::query()->create([...$data->values, 'id' => $employee->id]);
            $employee->user->forceFill(['user_type' => UserType::Doctor])->save();
            $employee->user->assignRole(RoleName::Doctor->value);
        });
        $this->roles->forgetAccess($employee->user);

        return $this->find($employee->id);
    }

    public function update(Doctor $doctor, DoctorData $data): Doctor
    {
        $doctor->fill($data->values)->save();

        return $this->find($doctor->id);
    }

    public function setAcceptingPatients(Doctor $doctor, bool $accepting): Doctor
    {
        $doctor->forceFill(['is_accepting_patients' => $accepting])->save();

        return $this->find($doctor->id);
    }

    /**
     * @return Builder<Doctor>
     */
    private function doctors(): Builder
    {
        $query = Doctor::query()
            ->select('doctors.*')
            ->join('persons', 'persons.id', '=', 'doctors.id')
            ->where('persons.is_deleted', false)
            ->with(self::RELATIONS);

        return OwnershipScope::apply($query, 'doctors', Auth::user());
    }
}
