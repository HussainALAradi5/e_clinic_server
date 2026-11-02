<?php

use App\Enums\HR\AttendanceStatus;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Interfaces\HR\AttendanceServiceInterface;
use App\Models\Accounting\JournalEntry;
use App\Models\Appointments\ScheduleException;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeSalaryComponent;
use App\Models\Identity\User;
use App\Notifications\Identity\Models\Notification as AppNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FiscalYearSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\FrontDesk;

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->seed([CountrySeeder::class, RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);
    Notification::fake();
    Storage::fake('local');
    // Sunday 1 Nov 2026, 08:00 in Bahrain (the clinic week is Sunday–Thursday).
    $this->travelTo(CarbonImmutable::parse('2026-11-01 05:00:00', 'UTC'));
    $this->seed(FiscalYearSeeder::class);
});

/** Staff member (hired 2025, 08:00–16:00, Fridays and Saturdays off, 1,000 BHD); HR and finance with 2FA. */
function hrStaff(RoleName $role = RoleName::Nurse, UserType $type = UserType::MedicalStaff): Employee
{
    $employee = FrontDesk::employee($type, $role);
    if (in_array($role, [RoleName::HrManager, RoleName::Accountant], true)) {
        User::query()->whereKey($employee->id)->update([
            'two_factor_secret' => encrypt((new Google2FA)->generateSecretKey(32)), 'two_factor_confirmed_at' => now(),
        ]);
    }

    return $employee;
}

function hrAs(Employee $employee): void
{
    Sanctum::actingAs(User::query()->findOrFail($employee->id));
}

/** Clinic clock at a local time today (Bahrain = UTC+3). */
function hrAt(string $time, string $date = '2026-11-01'): void
{
    test()->travelTo(CarbonImmutable::parse("{$date} {$time}", 'Asia/Bahrain')->utc());
}

/** @return array<string, string> */
function hrLeaveTypes(): array
{
    $types = [
        ['code' => 'ANNUAL', 'name' => 'Annual leave', 'annual_quota_days' => 30, 'is_paid' => true, 'requires_attachment' => false, 'max_carry_forward_days' => 10, 'min_notice_days' => 7],
        ['code' => 'SICK', 'name' => 'Sick leave', 'annual_quota_days' => 15, 'is_paid' => true, 'requires_attachment' => true, 'max_carry_forward_days' => 0, 'min_notice_days' => 0],
        ['code' => 'UNPAID', 'name' => 'Unpaid leave', 'annual_quota_days' => 0, 'is_paid' => false, 'requires_attachment' => false, 'max_carry_forward_days' => 0, 'min_notice_days' => 0],
    ];
    $ids = [];
    foreach ($types as $type) {
        $ids[$type['code']] = test()->postJson('/api/v1/hr/leaves/types', $type)->assertCreated()->json('data.id');
    }

    return $ids;
}

it('lets the phone clock in and out off the clinic network only inside the clinic geofence', function () {
    hrAs(hrStaff());
    hrAt('08:00');
    Config::set('hr.attendance.networks', ['10.20.0.0/16']);
    Config::set('hr.attendance.geofence', ['latitude' => 26.2235, 'longitude' => 50.5876, 'radius_meters' => 150]);

    // About 1 km away, a reading vaguer than the circle, or half a position: refused.
    $this->postJson('/api/v1/hr/attendance/check-in', ['latitude' => 26.2325, 'longitude' => 50.5876])
        ->assertForbidden()->assertJsonPath('error_code', 'ATTENDANCE_NETWORK_NOT_ALLOWED');
    $this->postJson('/api/v1/hr/attendance/check-in', ['latitude' => 26.2236, 'longitude' => 50.5877, 'accuracy' => 400])
        ->assertForbidden();
    $this->postJson('/api/v1/hr/attendance/check-in', ['latitude' => 26.2236])->assertStatus(422);

    // At the door (about 15 m): in, marked as a device check-in; out the same way.
    $this->postJson('/api/v1/hr/attendance/check-in', ['latitude' => 26.2236, 'longitude' => 50.5877, 'accuracy' => 12])
        ->assertOk()->assertJsonPath('data.source', 'device');
    hrAt('16:00');
    $this->postJson('/api/v1/hr/attendance/check-out', ['latitude' => 26.2236, 'longitude' => 50.5877])->assertOk();
});

it('checks staff in and out from the clinic network, with lateness, overtime approval by someone else and HR corrections', function () {
    $nurse = hrStaff();
    $hr = hrStaff(RoleName::HrManager, UserType::Hr);
    hrAs($nurse);

    hrAt('08:25');
    $this->postJson('/api/v1/hr/attendance/check-in')->assertOk()
        ->assertJsonPath('data.status', 'late')->assertJsonPath('data.late_minutes', 25)->assertJsonPath('data.source', 'web');
    $this->postJson('/api/v1/hr/attendance/check-in')->assertStatus(409)->assertJsonPath('error_code', 'ATTENDANCE_ALREADY_CHECKED_IN');
    hrAt('17:00');
    $out = $this->postJson('/api/v1/hr/attendance/check-out')->assertOk()
        ->assertJsonPath('data.worked_minutes', 515)->assertJsonPath('data.overtime_minutes', 60)->assertJsonPath('data.overtime_approved', false);
    $this->postJson('/api/v1/hr/attendance/check-out')->assertStatus(409)->assertJsonPath('error_code', 'ATTENDANCE_NOT_CHECKED_IN');
    $this->getJson('/api/v1/hr/attendance/today')->assertOk()->assertJsonPath('data.shift.start', '08:00')->assertJsonPath('data.attendance.status', 'late');
    $this->patchJson("/api/v1/hr/attendance/{$out->json('data.id')}/approve-overtime")->assertForbidden();

    // Only from the clinic network.
    Config::set('hr.attendance.networks', ['10.20.0.0/16']);
    $this->postJson('/api/v1/hr/attendance/check-in')->assertForbidden()->assertJsonPath('error_code', 'ATTENDANCE_NETWORK_NOT_ALLOWED');
    // Without a clinic geofence the phone's position does not help.
    $this->postJson('/api/v1/hr/attendance/check-in', ['latitude' => 26.2235, 'longitude' => 50.5876])->assertForbidden();
    Config::set('hr.attendance.networks', []);

    // HR approves the nurse's overtime, never their own.
    hrAs($hr);
    $this->patchJson("/api/v1/hr/attendance/{$out->json('data.id')}/approve-overtime")->assertOk()->assertJsonPath('data.overtime_approved', true);
    hrAt('09:00', '2026-11-02');
    $this->postJson('/api/v1/hr/attendance/check-in')->assertOk();
    hrAt('17:30', '2026-11-02');
    $own = $this->postJson('/api/v1/hr/attendance/check-out')->assertOk()->json('data.id');
    $this->patchJson("/api/v1/hr/attendance/{$own}/approve-overtime")->assertForbidden()->assertJsonPath('error_code', 'SELF_APPROVAL_NOT_ALLOWED');

    // A correction needs a reason; a day without times records what it was.
    $this->postJson('/api/v1/hr/attendance/manual', ['employee_id' => $nurse->id, 'date' => '2026-11-02', 'check_in' => '07:55'])->assertStatus(422);
    $this->postJson('/api/v1/hr/attendance/manual', ['employee_id' => $nurse->id, 'date' => '2026-11-02', 'check_in' => '07:55', 'check_out' => '16:05', 'reason' => 'Forgot to check in'])
        ->assertOk()->assertJsonPath('data.status', 'present')->assertJsonPath('data.worked_minutes', 490)->assertJsonPath('data.source', 'manual');

    // The nightly close: the nurse did not come on Tuesday, Friday is a day off.
    $close = app(AttendanceServiceInterface::class);
    expect($close->closeDay(CarbonImmutable::parse('2026-11-03')))->toBe(2)
        ->and(Attendance::query()->where('employee_id', $nurse->id)->whereDate('date', '2026-11-03')->value('status'))->toBe(AttendanceStatus::Absent);
    $close->closeDay(CarbonImmutable::parse('2026-11-06'));
    expect(Attendance::query()->where('employee_id', $nurse->id)->whereDate('date', '2026-11-06')->value('status'))->toBe(AttendanceStatus::Weekend);

    // Staff see their own days only.
    hrAs($nurse);
    $this->getJson('/api/v1/hr/attendance')->assertOk()->assertJsonCount(4, 'data');
    $this->getJson("/api/v1/hr/attendance/employees/{$hr->id}/summary")->assertNotFound();
    $this->getJson("/api/v1/hr/attendance/employees/{$nurse->id}/summary?year=2026&month=11")->assertOk()
        ->assertJsonPath('data.present_days', '2.0')->assertJsonPath('data.absent_days', '1.0')->assertJsonPath('data.approved_overtime_minutes', 60);
});

it('requests leave on working days with notice, overlap, balance and certificate checks, approved by someone else', function () {
    $nurse = hrStaff();
    $hr = hrStaff(RoleName::HrManager, UserType::Hr);
    hrAs($hr);
    $types = hrLeaveTypes();
    $doctor = FrontDesk::doctor();

    hrAs($nurse);
    $this->getJson("/api/v1/hr/leaves/balances/{$nurse->id}")->assertOk()
        ->assertJsonPath('data.0.leave_type.code', 'ANNUAL')->assertJsonPath('data.0.entitled_days', '30.0')->assertJsonPath('data.0.remaining_days', '30.0')
        ->assertJsonPath('data.2.leave_type.code', 'UNPAID')->assertJsonPath('data.2.remaining_days', null);
    $this->getJson("/api/v1/hr/leaves/balances/{$hr->id}")->assertNotFound();

    // Tue 10 – Mon 16 Nov: Friday and Saturday are not counted.
    $annual = $this->postJson('/api/v1/hr/leaves', ['leave_type_id' => $types['ANNUAL'], 'start_date' => '2026-11-10', 'end_date' => '2026-11-16', 'reason' => 'Family visit'])
        ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.total_days', '5.0');
    $this->postJson('/api/v1/hr/leaves', ['leave_type_id' => $types['UNPAID'], 'start_date' => '2026-11-12', 'end_date' => '2026-11-12'])->assertStatus(409)->assertJsonPath('error_code', 'LEAVE_OVERLAP');
    $this->postJson('/api/v1/hr/leaves', ['leave_type_id' => $types['ANNUAL'], 'start_date' => '2026-11-03', 'end_date' => '2026-11-03'])->assertStatus(422)->assertJsonValidationErrors('start_date');
    $this->postJson('/api/v1/hr/leaves', ['leave_type_id' => $types['ANNUAL'], 'start_date' => '2026-11-20', 'end_date' => '2026-12-31'])->assertStatus(422)->assertJsonPath('error_code', 'LEAVE_INSUFFICIENT_BALANCE');
    $this->postJson('/api/v1/hr/leaves', ['leave_type_id' => $types['UNPAID'], 'start_date' => '2026-12-31', 'end_date' => '2027-01-02'])->assertStatus(422)->assertJsonValidationErrors('end_date');
    $this->getJson("/api/v1/hr/leaves/balances/{$nurse->id}")->assertJsonPath('data.0.pending_days', '5.0')->assertJsonPath('data.0.remaining_days', '25.0');

    // Sick leave waits as a draft until its certificate is attached.
    $sick = $this->postJson('/api/v1/hr/leaves', ['leave_type_id' => $types['SICK'], 'start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'is_half_day' => true])
        ->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.total_days', '0.5')->json('data.id');
    $this->patchJson("/api/v1/hr/leaves/{$sick}/submit")->assertStatus(422)->assertJsonPath('error_code', 'LEAVE_ATTACHMENT_REQUIRED');
    $this->post('/api/v1/attachments', [
        'files' => [UploadedFile::fake()->createWithContent('certificate.pdf', "%PDF-1.4\nsick note\n%%EOF")],
        'attachable_type' => 'leave_request', 'attachable_id' => $sick, 'category' => 'medical_certificate',
    ], ['Accept' => 'application/json'])->assertCreated();
    $this->patchJson("/api/v1/hr/leaves/{$sick}/submit")->assertOk()->assertJsonPath('data.status', 'pending');
    $this->patchJson("/api/v1/hr/leaves/{$sick}/approve")->assertForbidden();
    $this->patchJson("/api/v1/hr/leaves/{$sick}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');

    // HR is told, approves; the days become used and are marked on attendance.
    expect(AppNotification::query()->where('template', 'leave.requested')->count())->toBeGreaterThan(0);
    hrAs($hr);
    $this->getJson('/api/v1/hr/leaves?filter[status]=pending')->assertOk()->assertJsonCount(1, 'data');
    $this->patchJson("/api/v1/hr/leaves/{$annual->json('data.id')}/approve", ['notes' => 'Enjoy'])->assertOk()
        ->assertJsonPath('data.status', 'approved')->assertJsonPath('data.reviewed_by.id', $hr->id);
    expect(Attendance::query()->where('employee_id', $nurse->id)->where('status', 'on_leave')->count())->toBe(5);
    $this->getJson("/api/v1/hr/leaves/balances/{$nurse->id}")->assertJsonPath('data.0.used_days', '5.0')->assertJsonPath('data.0.pending_days', '0.0');

    // Nobody decides their own leave; a provider on leave gets days off on the schedule.
    $mine = $this->postJson('/api/v1/hr/leaves', ['leave_type_id' => $types['UNPAID'], 'start_date' => '2026-11-04', 'end_date' => '2026-11-04'])->assertCreated()->json('data.id');
    $this->patchJson("/api/v1/hr/leaves/{$mine}/approve")->assertForbidden()->assertJsonPath('error_code', 'SELF_APPROVAL_NOT_ALLOWED');
    $doctorLeave = $this->postJson('/api/v1/hr/leaves', ['employee_id' => $doctor->id, 'leave_type_id' => $types['ANNUAL'], 'start_date' => '2026-11-22', 'end_date' => '2026-11-23'])->assertCreated()->json('data.id');
    $this->patchJson("/api/v1/hr/leaves/{$doctorLeave}/approve")->assertOk();
    expect(ScheduleException::query()->where('provider_id', $doctor->id)->where('type', 'day_off')->count())->toBe(2);

    // An approved leave that has not started can be cancelled by HR: days and schedule restored.
    $this->patchJson("/api/v1/hr/leaves/{$doctorLeave}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
    expect(ScheduleException::query()->where('provider_id', $doctor->id)->count())->toBe(0);
    $this->getJson("/api/v1/hr/leaves/balances/{$doctor->id}")->assertJsonPath('data.0.used_days', '0.0');

    // New joiners get a pro-rated quota (hired in July: 6 of 12 months).
    $joiner = hrStaff();
    $joiner->forceFill(['hire_date' => '2026-07-15'])->save();
    $this->getJson("/api/v1/hr/leaves/balances/{$joiner->id}")->assertJsonPath('data.0.entitled_days', '15.0');
});

it('calculates, approves (posting the accrual) and pays a month of salaries, with absences, unpaid leave, overtime and insurance', function () {
    $nurse = hrStaff();
    $nurse->person->forceFill(['nationality_code' => 'BH'])->save();
    $hr = hrStaff(RoleName::HrManager, UserType::Hr);
    $accountant = hrStaff(RoleName::Accountant, UserType::Accountant);
    $hr->person->forceFill(['nationality_code' => 'IN'])->save();
    $accountant->person->forceFill(['nationality_code' => 'GB'])->save();
    EmployeeSalaryComponent::query()->create(['employee_id' => $nurse->id, 'type' => 'allowance', 'name' => 'Housing', 'amount' => '200', 'effective_from' => '2026-01-01', 'is_taxable' => false]);
    EmployeeSalaryComponent::query()->create(['employee_id' => $nurse->id, 'type' => 'deduction', 'name' => 'Staff loan', 'amount' => '50', 'effective_from' => '2026-01-01']);

    // October: one absence, one approved unpaid leave day, two approved overtime hours.
    hrAs($hr);
    $types = hrLeaveTypes();
    Attendance::query()->create(['employee_id' => $nurse->id, 'date' => '2026-10-05', 'status' => 'absent', 'source' => 'manual']);
    Attendance::query()->create(['employee_id' => $nurse->id, 'date' => '2026-10-06', 'status' => 'present', 'source' => 'web', 'overtime_minutes' => 120, 'overtime_approved' => true]);
    $leave = $this->postJson('/api/v1/hr/leaves', ['employee_id' => $nurse->id, 'leave_type_id' => $types['UNPAID'], 'start_date' => '2026-10-07', 'end_date' => '2026-10-07'])->assertCreated()->json('data.id');
    $this->patchJson("/api/v1/hr/leaves/{$leave}/approve")->assertOk();

    $run = $this->postJson('/api/v1/hr/payroll-runs', ['year' => 2026, 'month' => 10])->assertCreated()->assertJsonPath('data.code', 'PR-2026-10')->assertJsonPath('data.status', 'draft')->json('data.id');
    $this->postJson('/api/v1/hr/payroll-runs', ['year' => 2026, 'month' => 10])->assertStatus(409)->assertJsonPath('error_code', 'PAYROLL_PERIOD_EXISTS');
    $november = $this->postJson('/api/v1/hr/payroll-runs', ['year' => 2026, 'month' => 11])->assertCreated()->json('data.id');
    $this->postJson("/api/v1/hr/payroll-runs/{$november}/calculate")->assertStatus(422)->assertJsonPath('error_code', 'PAYROLL_PERIOD_NOT_ENDED');

    $calculated = $this->postJson("/api/v1/hr/payroll-runs/{$run}/calculate")->assertOk()->assertJsonPath('data.status', 'calculated')->assertJsonPath('data.employee_count', 3);
    $slip = collect($calculated->json('data.payslips'))->firstWhere('employee.id', $nurse->id);
    // 1000 basic + 200 housing + overtime 2 h × (1000/30/8) × 1.25 = 10.417 → gross 1210.417;
    // absence 33.333 + unpaid 33.333 + insurance 7 % of 1000 = 70 + loan 50 → net 1023.751.
    expect($slip['gross_salary']['amount'])->toBe('1210.417')
        ->and($slip['total_deductions']['amount'])->toBe('186.666')
        ->and($slip['net_salary']['amount'])->toBe('1023.751')
        ->and($slip['absent_days'])->toBe('1.0')->and($slip['unpaid_leave_days'])->toBe('1.0');

    // Staff do not see a payslip until the run is approved; whoever calculated cannot approve.
    hrAs($nurse);
    $this->getJson('/api/v1/hr/payslips')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/hr/payroll-runs')->assertForbidden();
    hrAs($hr);
    $this->postJson("/api/v1/hr/payroll-runs/{$run}/approve")->assertForbidden();
    hrAs($accountant);
    expect(AppNotification::query()->where('template', 'payroll.calculated')->exists())->toBeTrue();
    $this->postJson("/api/v1/hr/payroll-runs/{$run}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    $accrual = JournalEntry::query()->with('lines.account')->findOrFail($this->getJson("/api/v1/hr/payroll-runs/{$run}")->json('data.accrual_journal_entry_id'));
    $byCode = $accrual->lines->groupBy(fn ($l) => $l->account->code)->map(fn ($lines) => (string) $lines->sum(fn ($l) => (float) $l->debit - (float) $l->credit));
    expect($accrual->total_debit)->toBe($accrual->total_credit)
        ->and(round((float) $byCode['2300'], 3))->toBe(-90.0)
        ->and(round((float) $byCode['2400'], 3))->toBe(-50.0);

    // October's attendance is now read-only.
    hrAs($hr);
    $this->postJson('/api/v1/hr/attendance/manual', ['employee_id' => $nurse->id, 'date' => '2026-10-05', 'status' => 'present', 'reason' => 'Late correction'])
        ->assertStatus(409)->assertJsonPath('error_code', 'ATTENDANCE_LOCKED');

    hrAs($nurse);
    $mine = $this->getJson('/api/v1/hr/payslips')->assertOk()->assertJsonCount(1, 'data')->json('data.0.id');
    $this->getJson("/api/v1/hr/payslips/{$mine}")->assertOk()->assertJsonPath('data.net_salary.amount', '1023.751')->assertJsonPath('data.items.0.type', 'basic');
    $this->getJson("/api/v1/hr/payslips/{$slip['id']}")->assertOk();
    $other = collect($calculated->json('data.payslips'))->firstWhere('employee.id', $hr->id)['id'];
    $this->getJson("/api/v1/hr/payslips/{$other}")->assertNotFound();

    hrAs($accountant);
    $this->postJson("/api/v1/hr/payroll-runs/{$run}/mark-paid", ['reference' => 'WPS-2026-10'])->assertOk()
        ->assertJsonPath('data.status', 'paid')->assertJsonPath('data.payment_reference', 'WPS-2026-10');
    $this->postJson("/api/v1/hr/payroll-runs/{$run}/cancel", ['reason' => 'Too late'])->assertStatus(409);
    expect(AppNotification::query()->where('template', 'payroll.payslip_ready')->count())->toBe(3);
});

it('cancels an approved run by reversing its accrual, and carries a shortfall to the next month', function () {
    $nurse = hrStaff();
    $nurse->person->forceFill(['nationality_code' => 'IN'])->save();
    $hr = hrStaff(RoleName::HrManager, UserType::Hr);
    $accountant = hrStaff(RoleName::Accountant, UserType::Accountant);
    EmployeeSalaryComponent::query()->create(['employee_id' => $nurse->id, 'type' => 'deduction', 'name' => 'Advance recovery', 'amount' => '1500', 'effective_from' => '2026-09-01', 'effective_to' => '2026-09-30']);

    hrAs($hr);
    $september = $this->postJson('/api/v1/hr/payroll-runs', ['year' => 2026, 'month' => 9])->assertCreated()->json('data.id');
    $slip = collect($this->postJson("/api/v1/hr/payroll-runs/{$september}/calculate")->json('data.payslips'))->firstWhere('employee.id', $nurse->id);
    expect($slip['net_salary']['amount'])->toBe('0.000')->and($slip['carried_forward']['amount'])->toBe('510.000');

    hrAs($accountant);
    $this->postJson("/api/v1/hr/payroll-runs/{$september}/approve")->assertOk();
    hrAs($hr);
    $october = $this->postJson('/api/v1/hr/payroll-runs', ['year' => 2026, 'month' => 10])->assertCreated()->json('data.id');
    $next = collect($this->postJson("/api/v1/hr/payroll-runs/{$october}/calculate")->json('data.payslips'))->firstWhere('employee.id', $nurse->id);
    expect($next['net_salary']['amount'])->toBe('480.000');

    // Cancelling the approved September run reverses its accrual and reopens the month.
    $accrual = $this->getJson("/api/v1/hr/payroll-runs/{$september}")->json('data.accrual_journal_entry_id');
    $this->postJson("/api/v1/hr/payroll-runs/{$september}/cancel", ['reason' => 'Advance was wrong'])->assertOk()->assertJsonPath('data.status', 'cancelled');
    expect(JournalEntry::query()->findOrFail($accrual)->status->value)->toBe('reversed');
    $this->postJson('/api/v1/hr/payroll-runs', ['year' => 2026, 'month' => 9])->assertCreated()->assertJsonPath('data.code', 'PR-2026-09-2');
});
