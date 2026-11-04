<?php

use App\Dtos\Accounting\FiscalYearData;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Enums\Reference\CompanyType;
use App\Interfaces\Accounting\FiscalPeriodServiceInterface;
use App\Models\Appointments\Patient;
use App\Models\Identity\User;
use App\Models\Payments\BillableService;
use App\Models\Reference\Company;
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

function copayPost(string $url, array $payload = []): TestResponse
{
    return test()->postJson($url, $payload, ['Idempotency-Key' => (string) Str::uuid()]);
}

/** A patient insured at 80 % with a valid policy (expiry is changeable). */
function insuredPatient(string $expiry = '2027-06-30'): Patient
{
    $insurer = Company::query()->create(['type' => CompanyType::Insurer, 'name' => 'Gulf Insurance Group', 'country_code' => 'BH']);
    $patient = FrontDesk::patient();
    $patient->forceFill([
        'insurance_company_id' => $insurer->id, 'insurance_policy_number' => 'GIG-7781',
        'insurance_expiry_date' => $expiry, 'insurance_coverage_percent' => '80',
    ])->save();

    return $patient;
}

/** Checks a walk-in in and bills the visit (15.000, insurable) at check-in; returns the issued invoice id. */
function checkedInInvoice(Patient $patient): string
{
    Sanctum::actingAs(User::factory()->withRole(RoleName::Receptionist->value)->create());
    BillableService::query()->update(['is_insurable' => true]);
    $visit = copayPost('/api/v1/appointments', [
        'patient_id' => $patient->id, 'provider_id' => FrontDesk::doctor()->id, 'type' => 'consultation',
        'scheduled_date' => '2026-11-01', 'start_time' => '08:30', 'source' => 'walk_in',
    ])->assertCreated()->json('data.id');
    test()->patchJson("/api/v1/appointments/{$visit}/check-in")->assertOk();
    $draft = test()->postJson('/api/v1/billing/invoices/charge', ['source_type' => 'appointment', 'source_id' => $visit])->assertOk()->json('data.id');

    return copayPost("/api/v1/billing/invoices/{$draft}/issue")->assertOk()->json('data.id');
}

it('takes only the patient share at check-in, then lets the claim cover the rest', function () {
    $invoice = checkedInInvoice(insuredPatient());

    $this->getJson("/api/v1/billing/invoices/{$invoice}/coverage")->assertOk()
        ->assertJsonPath('data.covered', true)
        ->assertJsonPath('data.insurer', 'Gulf Insurance Group')
        ->assertJsonPath('data.coverage_percent', '80.00')
        ->assertJsonPath('data.insurer_share.amount', '12.000')
        ->assertJsonPath('data.patient_due.amount', '3.000');

    copayPost('/api/v1/billing/payments', ['invoice_id' => $invoice, 'amount' => '15.000', 'method' => 'card'])
        ->assertUnprocessable()->assertJsonPath('error_code', 'COPAY_EXCEEDED')->assertJsonPath('meta.patient_due', '3.000');
    copayPost('/api/v1/billing/payments', ['invoice_id' => $invoice, 'amount' => '3.000', 'method' => 'card'])->assertCreated();

    $this->getJson("/api/v1/billing/invoices/{$invoice}/coverage")->assertJsonPath('data.patient_due.amount', '0.000');
    $this->getJson("/api/v1/billing/invoices/{$invoice}")->assertJsonPath('data.balance_due.amount', '12.000');
    copayPost('/api/v1/billing/payments', ['invoice_id' => $invoice, 'amount' => '1.000', 'method' => 'card'])
        ->assertUnprocessable()->assertJsonPath('error_code', 'COPAY_EXCEEDED');
});

it('shows patients the split of their own invoices only', function () {
    $patient = insuredPatient();
    $invoice = checkedInInvoice($patient);
    $other = checkedInInvoice(FrontDesk::patient());

    Sanctum::actingAs(User::factory()->state(['id' => $patient->id, 'user_type' => UserType::Patient])->withRole(RoleName::Patient->value)->create());
    $this->getJson("/api/v1/billing/invoices/{$invoice}/coverage")->assertOk()->assertJsonPath('data.patient_share.amount', '3.000');
    $this->getJson("/api/v1/billing/invoices/{$other}/coverage")->assertNotFound();
});

it('lets the patient choose to pay the insurer share too, noted on the payment', function () {
    $invoice = checkedInInvoice(insuredPatient());

    copayPost('/api/v1/billing/payments', ['invoice_id' => $invoice, 'amount' => '15.000', 'method' => 'card', 'pay_insurer_share' => true])
        ->assertCreated()->assertJsonPath('data.notes', "Patient paid the insurer's share too");
    $this->getJson("/api/v1/billing/invoices/{$invoice}")->assertJsonPath('data.status', 'paid');
});

it('bills the whole visit to the patient when the policy expired before the visit', function () {
    $invoice = checkedInInvoice(insuredPatient('2026-10-31'));

    $this->getJson("/api/v1/billing/invoices/{$invoice}/coverage")
        ->assertJsonPath('data.covered', false)->assertJsonPath('data.reason', 'expired')
        ->assertJsonPath('data.patient_due.amount', '15.000');
    copayPost('/api/v1/billing/payments', ['invoice_id' => $invoice, 'amount' => '15.000', 'method' => 'card'])->assertCreated();
});

it('pays the co-pay from the patient balance by default', function () {
    $patient = insuredPatient();
    $invoice = checkedInInvoice($patient);
    copayPost("/api/v1/billing/patients/{$patient->id}/wallet/deposits", ['amount' => '20.000', 'method' => 'card'])->assertCreated();

    copayPost("/api/v1/billing/invoices/{$invoice}/pay-from-balance")->assertCreated()->assertJsonPath('data.amount.amount', '3.000');
    $this->getJson("/api/v1/billing/patients/{$patient->id}/wallet")->assertJsonPath('meta.balance.amount', '17.000');
});
