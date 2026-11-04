<?php

use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Models\Accounting\JournalEntry;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\Identity\Notification as AppNotification;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FiscalYearSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\FrontDesk;

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->seed([CountrySeeder::class, RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);
    Notification::fake();
    // Sunday 1 Nov 2026 in Bahrain (the clinic week is Sunday–Thursday); the nurse works 08:00–16:00.
    $this->travelTo(CarbonImmutable::parse('2026-11-01 05:00:00', 'UTC'));
    $this->seed(FiscalYearSeeder::class);
});

/** Nurse (1,000 BHD, 08:00–16:00), or HR / an accountant with 2FA confirmed. */
function requestStaff(RoleName $role = RoleName::Nurse, UserType $type = UserType::MedicalStaff): Employee
{
    $employee = FrontDesk::employee($type, $role);
    if (in_array($role, [RoleName::HrManager, RoleName::Accountant], true)) {
        User::query()->whereKey($employee->id)->update(['two_factor_secret' => encrypt((new Google2FA)->generateSecretKey(32)), 'two_factor_confirmed_at' => now()]);
    }

    return $employee;
}

function requestAs(Employee $employee): void
{
    Sanctum::actingAs(User::query()->findOrFail($employee->id));
}

function requestAt(string $time, string $date = '2026-11-01'): void
{
    test()->travelTo(CarbonImmutable::parse("{$date} {$time}", 'Asia/Bahrain')->utc());
}

it('lets HR and admins switch late excuses and overtime requests and set the overtime pay per hour; nobody else', function () {
    requestAs(requestStaff());
    $this->getJson('/api/v1/hr/settings')->assertForbidden();
    $this->putJson('/api/v1/hr/settings/overtime', ['is_enabled' => true])->assertForbidden();

    requestAs(requestStaff(RoleName::HrManager, UserType::Hr));
    $this->getJson('/api/v1/hr/settings')->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.key', 'late_excuse')->assertJsonPath('data.0.is_enabled', false)->assertJsonPath('data.0.label', 'Late excuse requests')
        ->assertJsonPath('meta.total', 2);
    $this->putJson('/api/v1/hr/settings/overtime', ['is_enabled' => true, 'amount' => '2.5'])->assertOk()
        ->assertJsonPath('data.is_enabled', true)->assertJsonPath('data.amount.amount', '2.500')->assertJsonPath('data.has_amount', true);
    // Only overtime has an amount; unknown keys do not exist; the value is a real on / off.
    $this->putJson('/api/v1/hr/settings/late_excuse', ['is_enabled' => true, 'amount' => '1'])->assertStatus(422);
    $this->putJson('/api/v1/hr/settings/nights', ['is_enabled' => true])->assertNotFound();
    $this->putJson('/api/v1/hr/settings/late_excuse', ['is_enabled' => 'maybe'])->assertStatus(422);
    $this->putJson('/api/v1/hr/settings/late_excuse', ['is_enabled' => true])->assertOk()->assertJsonPath('data.amount', null);
    $this->getJson('/api/v1/hr/settings/late_excuse')->assertOk()->assertJsonPath('data.is_enabled', true);
});

it('excuses a late arrival on request, reviewed by HR with a reason; never by the requester', function () {
    $nurse = requestStaff();
    $hr = requestStaff(RoleName::HrManager, UserType::Hr);
    requestAs($nurse);
    requestAt('08:30');
    $day = $this->postJson('/api/v1/hr/attendance/check-in')->assertOk()->assertJsonPath('data.late_minutes', 30)->json('data.id');

    // Switched off: nothing to ask.
    $this->getJson('/api/v1/hr/requests/askable')->assertOk()->assertJsonCount(0, 'data');
    $this->postJson('/api/v1/hr/requests', ['type' => 'late_excuse', 'attendance_id' => $day])->assertStatus(409)->assertJsonPath('error_code', 'STAFF_REQUEST_DISABLED');

    requestAs($hr);
    $this->putJson('/api/v1/hr/settings/late_excuse', ['is_enabled' => true])->assertOk();
    requestAs($nurse);
    $this->getJson('/api/v1/hr/requests/askable')->assertOk()->assertJsonPath('data.0.attendance.id', $day)->assertJsonPath('data.0.types', ['late_excuse']);
    // No overtime on the day, and overtime requests are off.
    $this->postJson('/api/v1/hr/requests', ['type' => 'overtime', 'attendance_id' => $day])->assertStatus(409);
    $request = $this->postJson('/api/v1/hr/requests', ['type' => 'late_excuse', 'attendance_id' => $day, 'reason' => 'Road closed on the causeway'])
        ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.minutes', 30)->assertJsonPath('data.reason', 'Road closed on the causeway')
        ->assertJsonPath('data.details.amount.amount', '2.083');
    expect($request->json('data.code'))->toStartWith('REQ-2026-');
    $id = $request->json('data.id');
    $this->postJson('/api/v1/hr/requests', ['type' => 'late_excuse', 'attendance_id' => $day])->assertStatus(409)->assertJsonPath('error_code', 'STAFF_REQUEST_EXISTS');
    $this->patchJson("/api/v1/hr/requests/{$id}/approve")->assertForbidden();
    expect(AppNotification::query()->where('template', 'staff_request.requested')->where('person_id', $hr->id)->exists())->toBeTrue();

    // HR: a reason is required to reject, optional to approve.
    requestAs($hr);
    $this->getJson('/api/v1/hr/requests?filter[status][eq]=pending')->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.employee.id', $nurse->id);
    $this->patchJson("/api/v1/hr/requests/{$id}/reject", ['reason' => ''])->assertStatus(422);
    $this->patchJson("/api/v1/hr/requests/{$id}/approve", ['reason' => 'Confirmed with the traffic report'])->assertOk()
        ->assertJsonPath('data.status', 'approved')->assertJsonPath('data.review_reason', 'Confirmed with the traffic report')->assertJsonPath('data.reviewed_by.id', $hr->id);
    expect(Attendance::query()->findOrFail($day)->late_excused)->toBeTrue()
        ->and(AppNotification::query()->where('template', 'staff_request.approved')->where('person_id', $nurse->id)->exists())->toBeTrue();
    $this->patchJson("/api/v1/hr/requests/{$id}/reject", ['reason' => 'Changed my mind'])->assertStatus(409);

    // HR's own late arrival goes to someone else.
    requestAt('08:40', '2026-11-02');
    $own = $this->postJson('/api/v1/hr/attendance/check-in')->assertOk()->json('data.id');
    $mine = $this->postJson('/api/v1/hr/requests', ['type' => 'late_excuse', 'attendance_id' => $own])->assertCreated()->json('data.id');
    $this->patchJson("/api/v1/hr/requests/{$mine}/approve")->assertForbidden()->assertJsonPath('error_code', 'SELF_APPROVAL_NOT_ALLOWED');

    // Staff see only their own requests, and cannot ask about someone else's day.
    requestAs($nurse);
    $this->getJson('/api/v1/hr/requests')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/hr/requests/{$mine}")->assertNotFound();
    $this->postJson('/api/v1/hr/requests', ['type' => 'late_excuse', 'attendance_id' => $own])->assertNotFound();
    $this->patchJson("/api/v1/hr/requests/{$mine}/cancel")->assertNotFound();
});

it('pays overtime only through an approved request when overtime requests are on, at the HR rate per hour', function () {
    $nurse = requestStaff();
    $hr = requestStaff(RoleName::HrManager, UserType::Hr);
    requestAs($hr);
    $this->putJson('/api/v1/hr/settings/overtime', ['is_enabled' => true, 'amount' => '3'])->assertOk();

    requestAs($nurse);
    requestAt('08:00');
    $this->postJson('/api/v1/hr/attendance/check-in')->assertOk();
    requestAt('17:30');
    $first = $this->postJson('/api/v1/hr/attendance/check-out')->assertOk()->assertJsonPath('data.overtime_minutes', 90)->json('data.id');
    requestAt('08:00', '2026-11-02');
    $this->postJson('/api/v1/hr/attendance/check-in')->assertOk();
    requestAt('17:00', '2026-11-02');
    $second = $this->postJson('/api/v1/hr/attendance/check-out')->assertOk()->json('data.id');

    // HR cannot approve overtime directly while requests are on.
    requestAs($hr);
    $this->patchJson("/api/v1/hr/attendance/{$first}/approve-overtime")->assertStatus(409)->assertJsonPath('error_code', 'OVERTIME_NEEDS_REQUEST');

    requestAs($nurse);
    $this->getJson('/api/v1/hr/requests/askable')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.types', ['overtime']);
    $paid = $this->postJson('/api/v1/hr/requests', ['type' => 'overtime', 'attendance_id' => $first, 'reason' => 'Emergency patient'])->assertCreated()
        ->assertJsonPath('data.details.rate', '3.000')->assertJsonPath('data.details.amount.amount', '4.500')->json('data.id');
    $refused = $this->postJson('/api/v1/hr/requests', ['type' => 'overtime', 'attendance_id' => $second])->assertCreated()->json('data.id');
    // Withdrawn and asked again.
    $this->patchJson("/api/v1/hr/requests/{$refused}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
    $refused = $this->postJson('/api/v1/hr/requests', ['type' => 'overtime', 'attendance_id' => $second])->assertCreated()->json('data.id');

    requestAs($hr);
    $this->patchJson("/api/v1/hr/requests/{$paid}/approve")->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.review_reason', null);
    $day = Attendance::query()->findOrFail($first);
    expect($day->overtime_approved)->toBeTrue()->and((string) $day->overtime_amount?->getAmount())->toBe('4.500');
    $this->patchJson("/api/v1/hr/requests/{$refused}/reject", ['reason' => 'Not authorised in advance'])->assertOk()
        ->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.review_reason', 'Not authorised in advance');
    expect(Attendance::query()->findOrFail($second)->overtime_approved)->toBeFalse();

    // A rejected day is final.
    requestAs($nurse);
    $this->postJson('/api/v1/hr/requests', ['type' => 'overtime', 'attendance_id' => $second])->assertStatus(409)->assertJsonPath('error_code', 'STAFF_REQUEST_EXISTS');
    $this->getJson('/api/v1/hr/requests/askable')->assertOk()->assertJsonCount(0, 'data');
});

it('deducts late minutes HR did not excuse and pays overtime at the amount kept on each day', function () {
    $nurse = requestStaff();
    $hr = requestStaff(RoleName::HrManager, UserType::Hr);
    requestAs($hr);
    $this->putJson('/api/v1/hr/settings/late_excuse', ['is_enabled' => true])->assertOk();
    Attendance::query()->create(['employee_id' => $nurse->id, 'date' => '2026-10-05', 'status' => 'late', 'source' => 'web', 'late_minutes' => 30]);
    Attendance::query()->create(['employee_id' => $nurse->id, 'date' => '2026-10-06', 'status' => 'late', 'source' => 'web', 'late_minutes' => 45, 'late_excused' => true]);
    Attendance::query()->create(['employee_id' => $nurse->id, 'date' => '2026-10-07', 'status' => 'present', 'source' => 'web', 'overtime_minutes' => 120, 'overtime_approved' => true, 'overtime_amount' => '6']);

    $run = $this->postJson('/api/v1/hr/payroll-runs', ['year' => 2026, 'month' => 10])->assertCreated()->json('data.id');
    $slip = collect($this->postJson("/api/v1/hr/payroll-runs/{$run}/calculate")->assertOk()->json('data.payslips'))->firstWhere('employee.id', $nurse->id);
    $lines = collect($this->getJson("/api/v1/hr/payslips/{$slip['id']}")->assertOk()->json('data.items'))->keyBy('type');
    // Only the 30 unexcused minutes: 0.5 h × (1000 / 30 / 8) = 2.083; overtime as kept on the day.
    expect($lines['lateness']['amount']['amount'])->toBe('2.083')
        ->and($lines['overtime']['amount']['amount'])->toBe('6.000');

    // Approving posts the accrual: lateness is earned pay not paid, like an absence, so it lowers the salary expense.
    requestAs(requestStaff(RoleName::Accountant, UserType::Accountant));
    $this->postJson("/api/v1/hr/payroll-runs/{$run}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    $accrual = JournalEntry::query()->findOrFail($this->getJson("/api/v1/hr/payroll-runs/{$run}")->json('data.accrual_journal_entry_id'));
    expect($accrual->total_debit)->toBe($accrual->total_credit);
});

it('lets HR reject requests in bulk and export them at /hr/requests; staff cannot', function () {
    $nurse = requestStaff();
    $hr = requestStaff(RoleName::HrManager, UserType::Hr);
    requestAs($hr);
    $this->putJson('/api/v1/hr/settings/late_excuse', ['is_enabled' => true])->assertOk();
    requestAs($nurse);
    requestAt('08:30');
    $day = $this->postJson('/api/v1/hr/attendance/check-in')->assertOk()->json('data.id');
    $id = $this->postJson('/api/v1/hr/requests', ['type' => 'late_excuse', 'attendance_id' => $day])->assertCreated()->json('data.id');

    $this->postJson('/api/v1/hr/requests/bulk', ['action' => 'reject', 'reason' => 'No proof', 'ids' => [$id]])->assertForbidden();
    $this->postJson('/api/v1/hr/requests/export', ['format' => 'csv'])->assertForbidden();

    requestAs($hr);
    $this->postJson('/api/v1/hr/requests/bulk', ['action' => 'reject', 'reason' => 'No proof', 'ids' => [$id]])->assertOk()->assertJsonPath('data.succeeded', 1);
    $this->getJson("/api/v1/hr/requests/{$id}")->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.review_reason', 'No proof');
    $this->postJson('/api/v1/hr/requests/export', ['format' => 'csv'])->assertOk();
});
