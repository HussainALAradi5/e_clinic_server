<?php

declare(strict_types=1);

namespace App\Modules\HR;

use App\Modules\HR\Attendance\Contracts\AttendanceServiceInterface;
use App\Modules\HR\Attendance\Http\Resources\AttendanceResource;
use App\Modules\HR\Attendance\Models\Attendance;
use App\Modules\HR\Attendance\Services\AttendanceService;
use App\Modules\HR\Departments\Contracts\DepartmentServiceInterface;
use App\Modules\HR\Departments\Http\Resources\DepartmentResource;
use App\Modules\HR\Departments\Models\Department;
use App\Modules\HR\Departments\Services\DepartmentService;
use App\Modules\HR\Employees\Contracts\EmployeeServiceInterface;
use App\Modules\HR\Employees\Events\EmployeeHired;
use App\Modules\HR\Employees\Http\Resources\EmployeeResource;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\HR\Employees\Services\EmployeeService;
use App\Modules\HR\Leaves\Contracts\LeaveServiceInterface;
use App\Modules\HR\Leaves\Events\LeaveStatusChanged;
use App\Modules\HR\Leaves\Http\Resources\LeaveRequestResource;
use App\Modules\HR\Leaves\Listeners\OpenLeaveBalances;
use App\Modules\HR\Leaves\Listeners\SendLeaveNotifications;
use App\Modules\HR\Leaves\Models\LeaveRequest;
use App\Modules\HR\Leaves\Services\LeaveService;
use App\Modules\HR\Payroll\Contracts\PayrollServiceInterface;
use App\Modules\HR\Payroll\Events\PayrollStatusChanged;
use App\Modules\HR\Payroll\Http\Resources\PayslipResource;
use App\Modules\HR\Payroll\Listeners\SendPayrollNotifications;
use App\Modules\HR\Payroll\Models\PayrollRun;
use App\Modules\HR\Payroll\Models\Payslip;
use App\Modules\HR\Payroll\Services\PayrollService;
use App\Modules\HR\Requests\Contracts\StaffRequestServiceInterface;
use App\Modules\HR\Requests\Events\StaffRequestChanged;
use App\Modules\HR\Requests\Http\Resources\StaffRequestResource;
use App\Modules\HR\Requests\Listeners\SendStaffRequestNotifications;
use App\Modules\HR\Requests\Models\StaffRequest;
use App\Modules\HR\Requests\Services\StaffRequestService;
use App\Modules\HR\Settings\Contracts\SettingServiceInterface;
use App\Modules\HR\Settings\Services\SettingService;
use App\Modules\HR\Shifts\Contracts\ShiftServiceInterface;
use App\Modules\HR\Shifts\Http\Resources\ShiftResource;
use App\Modules\HR\Shifts\Models\Shift;
use App\Modules\HR\Shifts\Services\ShiftService;
use App\Shared\Bulk\BulkActionRegistry;
use App\Shared\Support\MoneyHelper;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Human Resources module: departments, shifts, employees, attendance, leaves and monthly payroll.
 */
final class HumanResourcesServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        DepartmentServiceInterface::class => DepartmentService::class,
        ShiftServiceInterface::class => ShiftService::class,
        EmployeeServiceInterface::class => EmployeeService::class,
        AttendanceServiceInterface::class => AttendanceService::class,
        LeaveServiceInterface::class => LeaveService::class,
        PayrollServiceInterface::class => PayrollService::class,
        SettingServiceInterface::class => SettingService::class,
        StaffRequestServiceInterface::class => StaffRequestService::class,
    ];

    public function boot(BulkActionRegistry $bulk): void
    {
        Event::listen(EmployeeHired::class, OpenLeaveBalances::class);
        Event::listen(LeaveStatusChanged::class, SendLeaveNotifications::class);
        Event::listen(PayrollStatusChanged::class, SendPayrollNotifications::class);
        Event::listen(StaffRequestChanged::class, SendStaffRequestNotifications::class);

        $bulk->register('departments', Department::class, Department::listQueryConfig(), DepartmentService::class)
            ->exportable(DepartmentResource::class, fn (Builder $q) => $q->with(['parent', 'manager.user.person'])->withCount('employees'), values: [
                'parent' => fn (Model $d): string => $d instanceof Department ? (string) $d->parent?->name : '',
                'manager' => fn (Model $d): string => $d instanceof Department ? (string) $d->manager?->user->person->full_name : '',
            ]);
        $bulk->register('shifts', Shift::class, Shift::listQueryConfig(), ShiftService::class)
            ->exportable(ShiftResource::class, fn (Builder $q) => $q->withCount('employees'));
        $bulk->register('employees', Employee::class, Employee::listQueryConfig())
            ->exportable(EmployeeResource::class, fn (Builder $q) => $q->select('employees.*')->join('persons', 'persons.id', '=', 'employees.id')->where('persons.is_deleted', false)->with(['person', 'user.roles', 'department']), values: [
                'department' => fn (Model $e): string => $e instanceof Employee ? $e->department->name : '',
            ]);

        $employee = fn (Model $m): string => $m->getRelationValue('employee') instanceof Employee ? $m->getRelationValue('employee')->person->full_name : '';
        $bulk->register('attendance', Attendance::class, Attendance::listQueryConfig(), genericActions: false)
            ->exportable(AttendanceResource::class, fn (Builder $q) => $q->with(['employee.person', 'employee.department', 'employee.shift']), values: ['employee' => $employee]);

        $bulk->register('leaves', LeaveRequest::class, LeaveRequest::listQueryConfig(), genericActions: false)
            ->action('approve', 'leaves.write&leaves.approve', [LeaveServiceInterface::class, 'approve'])
            ->action('reject', 'leaves.write&leaves.approve', [LeaveServiceInterface::class, 'reject'], needsReason: true)
            ->exportable(LeaveRequestResource::class, fn (Builder $q) => $q->with(LeaveService::RELATIONS), values: [
                'employee' => $employee,
                'leave_type' => fn (Model $r): string => $r instanceof LeaveRequest ? $r->leaveType->name : '',
            ]);

        $bulk->register('staff_requests', StaffRequest::class, StaffRequest::listQueryConfig(), genericActions: false)
            ->action('approve', 'staff_requests.write&staff_requests.review', [StaffRequestServiceInterface::class, 'approve'])
            ->action('reject', 'staff_requests.write&staff_requests.review', [StaffRequestServiceInterface::class, 'reject'], needsReason: true)
            ->exportable(StaffRequestResource::class, fn (Builder $q) => $q->with(StaffRequestService::RELATIONS), values: [
                'employee' => $employee,
                'type' => fn (Model $r): string => $r instanceof StaffRequest ? $r->type->label() : '',
                'date' => fn (Model $r): string => $r instanceof StaffRequest ? $r->attendance->date->toDateString() : '',
                'minutes' => fn (Model $r): string => $r instanceof StaffRequest ? (string) $r->minutes() : '',
            ]);

        $bulk->register('payroll_runs', PayrollRun::class, PayrollRun::listQueryConfig(), genericActions: false)
            ->readableBy('payroll.read_all')
            ->permissionsOf('payroll');

        $money = fn (string $field): Closure => fn (Model $m): string => MoneyHelper::format($m->getAttribute($field));
        $bulk->register('payroll', Payslip::class, Payslip::listQueryConfig(), genericActions: false)
            ->exportable(PayslipResource::class, fn (Builder $q) => $q->with(['employee.person', 'department']), values: [
                'employee' => $employee,
                'department' => fn (Model $p): string => $p instanceof Payslip ? $p->department->name : '',
                'gross_salary' => $money('gross_salary'),
                'total_deductions' => $money('total_deductions'),
                'net_salary' => $money('net_salary'),
            ]);
    }
}
