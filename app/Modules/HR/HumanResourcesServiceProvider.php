<?php

declare(strict_types=1);

namespace App\Modules\HR;

use App\Events\HR\EmployeeHired;
use App\Events\HR\LeaveStatusChanged;
use App\Events\HR\PayrollStatusChanged;
use App\Events\HR\StaffRequestChanged;
use App\Http\Resources\HR\AttendanceResource;
use App\Http\Resources\HR\DepartmentResource;
use App\Http\Resources\HR\EmployeeResource;
use App\Http\Resources\HR\LeaveRequestResource;
use App\Http\Resources\HR\PayslipResource;
use App\Http\Resources\HR\ShiftResource;
use App\Http\Resources\HR\StaffRequestResource;
use App\Interfaces\HR\AttendanceServiceInterface;
use App\Interfaces\HR\DepartmentServiceInterface;
use App\Interfaces\HR\EmployeeServiceInterface;
use App\Interfaces\HR\LeaveServiceInterface;
use App\Interfaces\HR\PayrollServiceInterface;
use App\Interfaces\HR\SettingServiceInterface;
use App\Interfaces\HR\ShiftServiceInterface;
use App\Interfaces\HR\StaffRequestServiceInterface;
use App\Listeners\HR\OpenLeaveBalances;
use App\Listeners\HR\SendLeaveNotifications;
use App\Listeners\HR\SendPayrollNotifications;
use App\Listeners\HR\SendStaffRequestNotifications;
use App\Models\HR\Attendance;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\LeaveRequest;
use App\Models\HR\PayrollRun;
use App\Models\HR\Payslip;
use App\Models\HR\Shift;
use App\Models\HR\StaffRequest;
use App\Services\Bulk\BulkActionRegistry;
use App\Services\HR\AttendanceService;
use App\Services\HR\DepartmentService;
use App\Services\HR\EmployeeService;
use App\Services\HR\LeaveService;
use App\Services\HR\PayrollService;
use App\Services\HR\SettingService;
use App\Services\HR\ShiftService;
use App\Services\HR\StaffRequestService;
use App\Utilities\Shared\MoneyHelper;
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
