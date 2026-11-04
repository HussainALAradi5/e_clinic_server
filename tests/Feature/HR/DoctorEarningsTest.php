<?php

use App\Dtos\Accounting\FiscalYearData;
use App\Enums\Identity\RoleName;
use App\Interfaces\Accounting\FiscalPeriodServiceInterface;
use App\Models\Appointments\Doctor;
use App\Models\Appointments\Patient;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FrontDesk;

beforeEach(function () {
    $this->seed([CountrySeeder::class, RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);
    Notification::fake();
    // Sunday 1 Nov 2026, 08:00 in Bahrain.
    $this->travelTo(CarbonImmutable::parse('2026-11-01 05:00:00', 'UTC'));
    app(FiscalPeriodServiceInterface::class)->createYear(FiscalYearData::calendar(2026));
});

function earnPost(string $url, array $payload = []): TestResponse
{
    return test()->postJson($url, $payload, ['Idempotency-Key' => (string) Str::uuid()]);
}

function earnAs(RoleName $role): User
{
    $user = User::factory()->withRole($role->value)->withTwoFactor()->create();
    Sanctum::actingAs($user);

    return $user;
}

/** A completed consultation (15.000) of the doctor, invoiced and issued (revenue booked to the doctor). */
function earnVisit(Doctor $doctor, Patient $patient, string $time): void
{
    earnAs(RoleName::Receptionist);
    $id = earnPost('/api/v1/appointments', [
        'patient_id' => $patient->id, 'provider_id' => $doctor->id, 'type' => 'consultation',
        'scheduled_date' => '2026-11-01', 'start_time' => $time, 'source' => 'walk_in',
    ])->assertCreated()->json('data.id');
    test()->patchJson("/api/v1/appointments/{$id}/check-in")->assertOk();
    Sanctum::actingAs(User::query()->findOrFail($doctor->id));
    test()->patchJson("/api/v1/appointments/{$id}/start")->assertOk();
    test()->patchJson("/api/v1/appointments/{$id}/complete", [])->assertOk();
    earnAs(RoleName::Receptionist);
    $draft = test()->getJson("/api/v1/billing/invoices?filter[patient_id][eq]={$patient->id}&filter[status][eq]=draft")->json('data.0.id');
    earnPost("/api/v1/billing/invoices/{$draft}/issue")->assertOk();
}

it('pays a doctor a share of the revenue booked to them, and lets only salary managers set it', function () {
    $doctor = FrontDesk::doctor();
    earnVisit($doctor, FrontDesk::patient(), '08:30');
    earnVisit($doctor, FrontDesk::patient(), '09:00');

    earnAs(RoleName::Receptionist);
    $this->putJson("/api/v1/hr/doctors/{$doctor->id}/commission", ['commission_type' => 'percent', 'commission_value' => '20'])->assertForbidden();
    earnAs(RoleName::HrManager);
    $this->putJson("/api/v1/hr/doctors/{$doctor->id}/commission", ['commission_type' => 'percent', 'commission_value' => '120'])->assertUnprocessable();
    $this->putJson("/api/v1/hr/doctors/{$doctor->id}/commission", ['commission_type' => 'percent', 'commission_value' => '20'])->assertOk();

    $row = collect($this->getJson('/api/v1/hr/doctor-earnings?year=2026&month=11')->assertOk()->json('data'))->firstWhere('doctor.id', $doctor->id);
    expect($row['revenue']['amount'])->toBe('30.000')
        ->and($row['visits'])->toBe(2)
        ->and($row['commission']['amount'])->toBe('6.000');
});

it('pays per completed visit, shows doctors only their own row, and puts the commission on the payslip', function () {
    $doctor = FrontDesk::doctor();
    $other = FrontDesk::doctor();
    earnVisit($doctor, FrontDesk::patient(), '08:30');
    earnAs(RoleName::HrManager);
    $this->putJson("/api/v1/hr/doctors/{$doctor->id}/commission", ['commission_type' => 'per_visit', 'commission_value' => '2.500'])->assertOk();

    Sanctum::actingAs(User::query()->findOrFail($doctor->id));
    $this->getJson('/api/v1/hr/doctor-earnings?year=2026&month=11')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.doctor.id', $doctor->id)
        ->assertJsonPath('data.0.commission.amount', '2.500');

    $this->travelTo(CarbonImmutable::parse('2026-12-02 05:00:00', 'UTC'));
    earnAs(RoleName::HrManager);
    $run = $this->postJson('/api/v1/hr/payroll-runs', ['year' => 2026, 'month' => 11])->assertCreated()->json('data.id');
    $this->postJson("/api/v1/hr/payroll-runs/{$run}/calculate")->assertOk();
    $slips = collect($this->getJson("/api/v1/hr/payslips?filter[payroll_run_id][eq]={$run}&per_page=50")->assertOk()->json('data'));
    $mine = $slips->firstWhere('employee.id', $doctor->id) ?? $slips->firstWhere('employee_id', $doctor->id);
    expect($mine)->not->toBeNull();
    $items = collect($this->getJson("/api/v1/hr/payslips/{$mine['id']}")->json('data.items'));
    expect($items->firstWhere('type', 'commission')['amount']['amount'] ?? null)->toBe('2.500')
        ->and($slips->firstWhere('employee.id', $other->id))->not->toBeNull();
});
