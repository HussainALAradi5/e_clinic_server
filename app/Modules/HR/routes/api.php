<?php

use App\Modules\HR\Attendance\Http\Controllers\AttendanceController;
use App\Modules\HR\Departments\Http\Controllers\DepartmentController;
use App\Modules\HR\Employees\Http\Controllers\EmployeeController;
use App\Modules\HR\Leaves\Http\Controllers\LeaveController;
use App\Modules\HR\Payroll\Http\Controllers\PayrollController;
use App\Modules\HR\Shifts\Http\Controllers\ShiftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Human Resources API (prefix /api/v1/hr) — Departments, Shifts, Employees, Attendance, Leaves, Payroll
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('hr')->name('hr.')->group(function (): void {

    // Shifts (fixed / flexible) and who is on them
    Route::prefix('shifts')->name('shifts.')->controller(ShiftController::class)->group(function (): void {
        Route::middleware(['access:shifts.read_all|shifts.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{shift}', 'show')->whereUuid('shift')->name('show');
        });
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('/', 'store')->middleware('access:shifts.create')->name('store');
            Route::put('{shift}', 'update')->whereUuid('shift')->middleware('access:shifts.write')->name('update');
            Route::delete('{shift}', 'destroy')->whereUuid('shift')->middleware('access:shifts.delete')->name('destroy');
            Route::post('{shift}/employees', 'assign')->whereUuid('shift')->middleware('access:shifts.write&employees.write')->name('employees.assign');
            Route::delete('{shift}/employees/{employee}', 'unassign')->whereUuid(['shift', 'employee'])->middleware('access:shifts.write&employees.write')->name('employees.unassign');
        });
    });
    Route::bulkActions('shifts', 'shifts')->middleware('access:shifts.write|shifts.delete');

    // Departments
    Route::prefix('departments')->name('departments.')->controller(DepartmentController::class)->group(function (): void {
        Route::middleware(['access:departments.read_all|departments.read_own', 'throttle:read'])->group(function (): void {
            Route::get('tree', 'tree')->name('tree');
            Route::get('/', 'index')->name('index');
            Route::get('{department}', 'show')->whereUuid('department')->name('show');
        });
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('/', 'store')->middleware('access:departments.create')->name('store');
            Route::put('{department}', 'update')->whereUuid('department')->middleware('access:departments.write')->name('update');
            Route::patch('{department}/archive', 'archive')->whereUuid('department')->middleware('access:departments.write')->name('archive');
            Route::patch('{department}/unarchive', 'unarchive')->whereUuid('department')->middleware('access:departments.write')->name('unarchive');
            Route::delete('{department}', 'destroy')->whereUuid('department')->middleware('access:departments.delete')->name('destroy');
        });
        Route::patch('{department}/restore', 'restore')->whereUuid('department')->middleware(['access:departments.delete', 'throttle:sensitive'])->name('restore');
    });
    Route::bulkActions('departments', 'departments')->middleware('access:departments.write|departments.delete');
    Route::dataTransfer('departments', 'departments');

    // Employees (salary routes and changes behind 2FA: privileged HR data)
    Route::prefix('employees')->name('employees.')->controller(EmployeeController::class)->group(function (): void {
        Route::middleware(['access:employees.read_all|employees.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{employee}', 'show')->whereUuid('employee')->name('show');
        });
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('/', 'store')->middleware('access:employees.create')->name('store');
            Route::put('{employee}', 'update')->whereUuid('employee')->middleware('access:employees.write')->name('update');
            Route::patch('{employee}/status', 'changeStatus')->whereUuid('employee')->middleware('access:employees.write')->name('status');
        });
        Route::middleware(['ensure.2fa', 'throttle:sensitive'])->group(function (): void {
            Route::post('{employee}/terminate', 'terminate')->whereUuid('employee')->middleware('access:employees.write&employees.terminate')->name('terminate');
            Route::post('{employee}/salary-components', 'addSalaryComponent')->whereUuid('employee')->middleware('access:employees.write&employees.manage_salary')->name('salary-components.store');
            Route::delete('salary-components/{component}', 'removeSalaryComponent')->whereUuid('component')->middleware('access:employees.write&employees.manage_salary')->name('salary-components.destroy');
        });
    });
    Route::middleware('ensure.2fa')->group(fn () => Route::dataTransfer('employees', 'employees'));

    // Attendance (self check-in / out from the clinic network; HR corrections and overtime approval)
    Route::prefix('attendance')->name('attendance.')->controller(AttendanceController::class)->group(function (): void {
        Route::middleware(['access:attendance.self', 'throttle:write'])->group(function (): void {
            Route::get('today', 'today')->name('today');
            Route::post('check-in', 'checkIn')->name('check-in');
            Route::post('check-out', 'checkOut')->name('check-out');
        });
        Route::middleware(['access:attendance.read_all|attendance.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('employees/{employee}/summary', 'summary')->whereUuid('employee')->name('summary');
        });
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('manual', 'manual')->middleware('access:attendance.create')->name('manual');
            Route::patch('{attendance}/approve-overtime', 'approveOvertime')->whereUuid('attendance')->middleware('access:attendance.write&attendance.approve_overtime')->name('approve-overtime');
        });
    });
    Route::dataTransfer('attendance', 'attendance');

    // Leaves (types, balances, requests through the approval workflow)
    Route::prefix('leaves')->name('leaves.')->controller(LeaveController::class)->group(function (): void {
        Route::get('types', 'types')->middleware('throttle:read')->name('types.index');
        Route::post('types', 'storeType')->middleware(['access:leaves.create&leaves.manage_types', 'throttle:write'])->name('types.store');
        Route::put('types/{type}', 'updateType')->whereUuid('type')->middleware(['access:leaves.write&leaves.manage_types', 'throttle:write'])->name('types.update');
        Route::middleware(['access:leaves.read_all|leaves.read_own', 'throttle:read'])->group(function (): void {
            Route::get('balances/{employee}', 'balances')->whereUuid('employee')->name('balances');
            Route::get('/', 'index')->name('index');
            Route::get('{leaveRequest}', 'show')->whereUuid('leaveRequest')->name('show');
        });
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('/', 'store')->middleware('access:leaves.create&leaves.request')->name('store');
            Route::patch('{leaveRequest}/submit', 'submit')->whereUuid('leaveRequest')->middleware('access:leaves.write&leaves.request')->name('submit');
            Route::patch('{leaveRequest}/cancel', 'cancel')->whereUuid('leaveRequest')->middleware('access:leaves.write&leaves.request')->name('cancel');
        });
        Route::middleware('throttle:sensitive')->group(function (): void {
            Route::patch('{leaveRequest}/approve', 'approve')->whereUuid('leaveRequest')->middleware('access:leaves.write&leaves.approve')->name('approve');
            Route::patch('{leaveRequest}/reject', 'reject')->whereUuid('leaveRequest')->middleware('access:leaves.write&leaves.approve')->name('reject');
        });
    });
    Route::bulkActions('leaves', 'leaves')->middleware('access:leaves.write&leaves.approve');
    Route::dataTransfer('leaves', 'leaves');

    // Payroll (runs behind 2FA; staff read their own payslips)
    Route::controller(PayrollController::class)->group(function (): void {
        Route::prefix('payroll-runs')->name('payroll-runs.')->group(function (): void {
            Route::middleware(['access:payroll.read_all', 'throttle:read'])->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('{payrollRun}', 'show')->whereUuid('payrollRun')->name('show');
            });
            Route::middleware(['ensure.2fa', 'throttle:sensitive'])->group(function (): void {
                Route::post('/', 'store')->middleware('access:payroll.create')->name('store');
                Route::post('{payrollRun}/calculate', 'calculate')->whereUuid('payrollRun')->middleware('access:payroll.create')->name('calculate');
                Route::post('{payrollRun}/approve', 'approve')->whereUuid('payrollRun')->middleware('access:payroll.write&payroll.approve')->name('approve');
                Route::post('{payrollRun}/mark-paid', 'markPaid')->whereUuid('payrollRun')->middleware('access:payroll.write&payroll.pay')->name('mark-paid');
                Route::post('{payrollRun}/cancel', 'cancel')->whereUuid('payrollRun')->middleware('access:payroll.create|payroll.approve')->name('cancel');
            });
        });
        Route::prefix('payslips')->name('payslips.')->middleware(['access:payroll.read_all|payroll.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'payslips')->name('index');
            Route::get('{payslip}', 'payslip')->whereUuid('payslip')->name('show');
        });
    });
    Route::middleware('ensure.2fa')->group(fn () => Route::dataTransfer('payslips', 'payroll'));
});
