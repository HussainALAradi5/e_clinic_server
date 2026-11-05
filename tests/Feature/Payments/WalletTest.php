<?php

use App\Dtos\Accounting\FiscalYearData;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Interfaces\Accounting\FiscalPeriodServiceInterface;
use App\Models\Accounting\JournalEntry;
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

function walletActAs(RoleName $role): User
{
    $user = User::factory()->withRole($role->value)->create();
    Sanctum::actingAs($user);

    return $user;
}

function walletPost(string $url, array $payload = [], ?string $key = null): TestResponse
{
    return test()->postJson($url, $payload, ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
}

function walletUrl(Patient $patient, string $path = ''): string
{
    return "/billing/patients/{$patient->id}/wallet".($path === '' ? '' : "/{$path}");
}

/** Issued invoice (15.000) of one completed consultation of the patient. */
function walletInvoice(Patient $patient): array
{
    $doctor = FrontDesk::doctor();
    walletActAs(RoleName::Receptionist);
    $id = walletPost('/appointments', [
        'patient_id' => $patient->id, 'provider_id' => $doctor->id, 'type' => 'consultation',
        'scheduled_date' => '2026-11-01', 'start_time' => '09:00', 'source' => 'walk_in',
    ])->assertCreated()->json('data.id');
    test()->patchJson("/appointments/{$id}/check-in")->assertOk();
    Sanctum::actingAs(User::query()->findOrFail($doctor->id));
    test()->patchJson("/appointments/{$id}/start")->assertOk();
    test()->patchJson("/appointments/{$id}/complete", [])->assertOk();
    walletActAs(RoleName::Receptionist);
    $draft = test()->getJson("/billing/invoices?filter[patient_id][eq]={$patient->id}")->json('data.0');

    return walletPost("/billing/invoices/{$draft['id']}/issue")->assertOk()->json('data');
}

it('takes a card deposit once per key, posts it to patient deposits and shows the balance', function () {
    $patient = FrontDesk::patient();
    walletActAs(RoleName::Receptionist);
    $key = (string) Str::uuid();
    $deposit = ['amount' => '20.000', 'method' => 'card'];

    $entry = walletPost(walletUrl($patient, 'deposits'), $deposit, $key)->assertCreated()
        ->assertJsonPath('data.type', 'deposit')
        ->assertJsonPath('data.balance_after.amount', '20.000')
        ->json('data');
    expect($entry['code'])->toStartWith('WLT-');
    walletPost(walletUrl($patient, 'deposits'), $deposit, $key)->assertCreated()->assertJsonPath('data.id', $entry['id']);

    $this->getJson(walletUrl($patient))->assertOk()
        ->assertJsonPath('meta.balance.amount', '20.000')
        ->assertJsonCount(1, 'data');
    $journal = JournalEntry::query()->with('lines.account')->latest('created_at')->firstOrFail();
    expect((string) $journal->lines->firstWhere('account.code', '2500')?->credit)->toBe('20.000');
});

it('keeps deposits within the minimum, the maximum balance and the allowed methods, and cash needs an open drawer', function () {
    $patient = FrontDesk::patient();
    walletActAs(RoleName::Receptionist);

    walletPost(walletUrl($patient, 'deposits'), ['amount' => '0.500', 'method' => 'card'])->assertStatus(422)->assertJsonPath('error_code', 'WALLET_LIMIT');
    walletPost(walletUrl($patient, 'deposits'), ['amount' => '1000.001', 'method' => 'card'])->assertStatus(422)->assertJsonPath('error_code', 'WALLET_LIMIT');
    walletPost(walletUrl($patient, 'deposits'), ['amount' => '5.000', 'method' => 'insurance'])->assertStatus(422)->assertJsonPath('error_code', 'WALLET_LIMIT');
    walletPost(walletUrl($patient, 'deposits'), ['amount' => '5.000', 'method' => 'bank_transfer'])->assertStatus(422)->assertJsonValidationErrors('reference_number');
    walletPost(walletUrl($patient, 'deposits'), ['amount' => '5.000', 'method' => 'cash'])->assertStatus(409)->assertJsonPath('error_code', 'NO_OPEN_CASH_SESSION');

    $this->postJson('/finance/cash-sessions', ['opening_float' => '0'])->assertCreated();
    walletPost(walletUrl($patient, 'deposits'), ['amount' => '5.000', 'method' => 'cash'])->assertCreated();
    $this->getJson('/finance/cash-sessions/current')->assertJsonPath('data.report.expected.amount', '5.000');
});

it('lets only the accountant pay money back and adjust, with reasons, limits and no negative balance', function () {
    $patient = FrontDesk::patient();
    walletActAs(RoleName::Receptionist);
    walletPost(walletUrl($patient, 'deposits'), ['amount' => '30.000', 'method' => 'card'])->assertCreated();
    walletPost(walletUrl($patient, 'withdrawals'), ['amount' => '5.000', 'method' => 'bank_transfer', 'reference_number' => 'TRX-1'])->assertForbidden();
    $this->postJson(walletUrl($patient, 'adjustments'), ['amount' => '5.000', 'direction' => 'credit', 'reason' => 'Goodwill credit'])->assertForbidden();

    walletActAs(RoleName::Accountant);
    walletPost(walletUrl($patient, 'withdrawals'), ['amount' => '40.000', 'method' => 'bank_transfer', 'reference_number' => 'TRX-1', 'reason' => 'Patient moved abroad'])
        ->assertStatus(409)->assertJsonPath('error_code', 'WALLET_INSUFFICIENT');
    walletPost(walletUrl($patient, 'withdrawals'), ['amount' => '10.000', 'method' => 'card', 'reason' => 'Patient moved abroad'])
        ->assertStatus(422)->assertJsonPath('error_code', 'WALLET_LIMIT');
    walletPost(walletUrl($patient, 'withdrawals'), ['amount' => '10.000', 'method' => 'bank_transfer', 'reference_number' => 'TRX-1', 'reason' => 'short'])
        ->assertStatus(422)->assertJsonPath('error_code', 'WALLET_LIMIT');
    walletPost(walletUrl($patient, 'withdrawals'), ['amount' => '10.000', 'method' => 'bank_transfer', 'reference_number' => 'TRX-1', 'reason' => 'Patient moved abroad'])
        ->assertCreated()->assertJsonPath('data.balance_after.amount', '20.000');

    $this->postJson(walletUrl($patient, 'adjustments'), ['amount' => '100.001', 'direction' => 'credit', 'reason' => 'Goodwill credit'])
        ->assertStatus(422)->assertJsonPath('error_code', 'WALLET_LIMIT');
    $this->postJson(walletUrl($patient, 'adjustments'), ['amount' => '25.000', 'direction' => 'debit', 'reason' => 'Correcting a typo'])
        ->assertStatus(409)->assertJsonPath('error_code', 'WALLET_INSUFFICIENT');
    $this->postJson(walletUrl($patient, 'adjustments'), ['amount' => '5.000', 'direction' => 'credit', 'reason' => 'Goodwill credit'])
        ->assertCreated()->assertJsonPath('data.type', 'adjustment_credit')->assertJsonPath('data.balance_after.amount', '25.000');
    expect(Patient::query()->findOrFail($patient->id)->wallet_balance->getAmount()->__toString())->toBe('25.000');
});

it('pays an invoice from the balance within what it holds, and a voided payment puts the money back', function () {
    $patient = FrontDesk::patient();
    $invoice = walletInvoice($patient);
    walletPost(walletUrl($patient, 'deposits'), ['amount' => '10.000', 'method' => 'card'])->assertCreated();

    walletPost("/billing/invoices/{$invoice['id']}/pay-from-balance", ['amount' => '12.000'])
        ->assertStatus(409)->assertJsonPath('error_code', 'WALLET_INSUFFICIENT');
    $payment = walletPost("/billing/invoices/{$invoice['id']}/pay-from-balance")->assertCreated()
        ->assertJsonPath('data.method', 'patient_balance')
        ->assertJsonPath('data.amount.amount', '10.000')
        ->json('data');
    $this->getJson("/billing/invoices/{$invoice['id']}")->assertJsonPath('data.balance_due.amount', '5.000');
    $this->getJson(walletUrl($patient))->assertJsonPath('meta.balance.amount', '0.000');

    walletActAs(RoleName::Accountant);
    $this->postJson("/billing/payments/{$payment['id']}/void", ['reason' => 'Wrong invoice'])->assertOk();
    $this->getJson(walletUrl($patient))->assertJsonPath('meta.balance.amount', '10.000')
        ->assertJsonPath('data.0.type', 'payment_reversal');
});

it('shows patients their own balance only', function () {
    $mine = FrontDesk::patient();
    $other = FrontDesk::patient();
    walletActAs(RoleName::Receptionist);
    walletPost(walletUrl($mine, 'deposits'), ['amount' => '5.000', 'method' => 'card'])->assertCreated();
    walletPost(walletUrl($other, 'deposits'), ['amount' => '7.000', 'method' => 'card'])->assertCreated();

    Sanctum::actingAs(User::factory()->state(['id' => $mine->id, 'user_type' => UserType::Patient])->withRole(RoleName::Patient->value)->create());
    $this->getJson(walletUrl($mine))->assertOk()->assertJsonPath('meta.balance.amount', '5.000')->assertJsonCount(1, 'data');
    $this->getJson(walletUrl($other))->assertNotFound();
    walletPost(walletUrl($mine, 'deposits'), ['amount' => '5.000', 'method' => 'card'])->assertForbidden();
});
