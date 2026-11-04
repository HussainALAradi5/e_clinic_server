<?php

use App\Dtos\Accounting\FiscalYearData;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Interfaces\Accounting\FiscalPeriodServiceInterface;
use App\Models\Appointments\Patient;
use App\Models\Identity\User;
use App\Models\Payments\BillableService;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FeeServicesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FrontDesk;

beforeEach(function () {
    $this->seed([CountrySeeder::class, RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class, FeeServicesSeeder::class]);
    Notification::fake();
    // Sunday 1 Nov 2026, 08:00 in Bahrain.
    $this->travelTo(CarbonImmutable::parse('2026-11-01 05:00:00', 'UTC'));
    app(FiscalPeriodServiceInterface::class)->createYear(FiscalYearData::calendar(2026));
});

function feeActAs(RoleName $role): User
{
    $user = User::factory()->withRole($role->value)->withTwoFactor()->create();
    Sanctum::actingAs($user);

    return $user;
}

/** A confirmed booking of the patient, made by reception. */
function feeVisit(Patient $patient, string $date, string $time): string
{
    feeActAs(RoleName::Receptionist);

    return test()->postJson('/api/v1/appointments', [
        'patient_id' => $patient->id, 'provider_id' => FrontDesk::doctor()->id, 'type' => 'consultation',
        'scheduled_date' => $date, 'start_time' => $time, 'source' => 'phone',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.id');
}

function feeCancel(string $visit, string $by): void
{
    test()->patchJson("/api/v1/appointments/{$visit}/cancel", ['reason' => 'Called the desk', 'cancelled_by' => $by])->assertOk();
}

it('charges a no-show once, from the price list, onto the patient draft invoice', function () {
    $patient = FrontDesk::patient();
    $visit = feeVisit($patient, '2026-11-01', '08:15');
    $this->travel(31)->minutes();
    $this->patchJson("/api/v1/appointments/{$visit}/no-show")->assertOk();

    $fee = $this->getJson('/api/v1/billing/appointment-fees')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'no_show')
        ->assertJsonPath('data.0.status', 'charged')
        ->assertJsonPath('data.0.amount.amount', '5.000')
        ->assertJsonPath('data.0.appointment.id', $visit)
        ->json('data.0');
    expect($fee['code'])->toStartWith('FEE-');
    // The fees page lists, filters and exports through the shared list system.
    $this->getJson('/api/v1/meta/resources/appointment_fees')->assertOk();
    $this->getJson("/api/v1/billing/invoices/{$fee['invoice']['id']}")
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.total.amount', '5.000')
        ->assertJsonPath('data.items.0.source.type', 'appointment_fee');
});

it('charges a late cancellation by the patient only, and tells reception before they confirm', function () {
    $patient = FrontDesk::patient();
    $soon = feeVisit($patient, '2026-11-01', '11:00');
    $later = feeVisit($patient, '2026-11-03', '10:00');
    $clinic = feeVisit($patient, '2026-11-01', '11:30');

    $this->getJson("/api/v1/billing/appointments/{$soon}/cancellation-fee")->assertOk()->assertJsonPath('data.amount.amount', '3.000');
    $this->getJson("/api/v1/billing/appointments/{$later}/cancellation-fee")->assertOk()->assertJsonPath('data.amount', null);

    feeCancel($soon, 'patient');
    feeCancel($later, 'patient');
    feeCancel($clinic, 'clinic');

    $this->getJson('/api/v1/billing/appointment-fees')->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'late_cancel')
        ->assertJsonPath('data.0.appointment.id', $soon)
        ->assertJsonPath('data.0.amount.amount', '3.000');
});

it('charges nothing when the clinic switched the fee off in the price list', function () {
    BillableService::query()->where('code', 'FEE-NO-SHOW')->update(['is_archived' => true]);
    $visit = feeVisit(FrontDesk::patient(), '2026-11-01', '08:15');
    $this->travel(31)->minutes();
    $this->patchJson("/api/v1/appointments/{$visit}/no-show")->assertOk();

    $this->getJson('/api/v1/billing/appointment-fees')->assertJsonCount(0, 'data');
});

it('lets only admins and accountants waive a fee, with a reason, while its invoice is a draft', function () {
    $patient = FrontDesk::patient();
    $visit = feeVisit($patient, '2026-11-01', '11:00');
    feeCancel($visit, 'patient');
    $fee = $this->getJson('/api/v1/billing/appointment-fees')->json('data.0');

    $this->postJson("/api/v1/billing/appointment-fees/{$fee['id']}/waive", ['reason' => 'Family emergency'])->assertForbidden();

    $accountant = feeActAs(RoleName::Accountant);
    $this->postJson("/api/v1/billing/appointment-fees/{$fee['id']}/waive", ['reason' => 'Sorry'])->assertUnprocessable();
    $this->postJson("/api/v1/billing/appointment-fees/{$fee['id']}/waive", ['reason' => 'Family emergency, hospital note seen'])->assertOk()
        ->assertJsonPath('data.status', 'waived')
        ->assertJsonPath('data.waived_by', $accountant->person->full_name)
        ->assertJsonPath('data.waiver_reason', 'Family emergency, hospital note seen');
    // The draft held only the fee: it is gone.
    $this->getJson("/api/v1/billing/invoices/{$fee['invoice']['id']}")->assertNotFound();
    $this->postJson("/api/v1/billing/appointment-fees/{$fee['id']}/waive", ['reason' => 'Family emergency again'])
        ->assertStatus(409)->assertJsonPath('error_code', 'FEE_ALREADY_WAIVED');
});

it('refuses to waive a fee that is already on an issued invoice', function () {
    $visit = feeVisit(FrontDesk::patient(), '2026-11-01', '11:00');
    feeCancel($visit, 'patient');
    $fee = $this->getJson('/api/v1/billing/appointment-fees')->json('data.0');
    $this->postJson("/api/v1/billing/invoices/{$fee['invoice']['id']}/issue", [], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();

    feeActAs(RoleName::Admin);
    $this->postJson("/api/v1/billing/appointment-fees/{$fee['id']}/waive", ['reason' => 'Family emergency, hospital note seen'])
        ->assertStatus(409)->assertJsonPath('error_code', 'FEE_NOT_WAIVABLE');
});

it('shows patients their own fees only', function () {
    $mine = FrontDesk::patient();
    $other = FrontDesk::patient();
    feeCancel(feeVisit($mine, '2026-11-01', '11:00'), 'patient');
    feeCancel(feeVisit($other, '2026-11-01', '11:30'), 'patient');

    Sanctum::actingAs(User::factory()->state(['id' => $mine->id, 'user_type' => UserType::Patient])->withRole(RoleName::Patient->value)->create());
    $this->getJson('/api/v1/billing/appointment-fees')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.patient.id', $mine->id);
});
