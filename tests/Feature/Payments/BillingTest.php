<?php

use App\Dtos\Accounting\FiscalYearData;
use App\Enums\Identity\RoleName;
use App\Enums\Reference\CompanyType;
use App\Interfaces\Accounting\FiscalPeriodServiceInterface;
use App\Models\Accounting\JournalEntry;
use App\Models\Appointments\Doctor;
use App\Models\Appointments\Icd10Code;
use App\Models\Appointments\MedicalRecord;
use App\Models\Appointments\Patient;
use App\Models\Appointments\RecordDiagnosis;
use App\Models\Identity\User;
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

function staff(RoleName $role): User
{
    return User::factory()->withRole($role->value)->create();
}

function actAs(User $user): User
{
    Sanctum::actingAs($user);

    return $user;
}

function keyed(string $url, array $payload = [], ?string $key = null): TestResponse
{
    return test()->postJson($url, $payload, ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
}

/** Books, checks in, starts and completes a consultation (15.000) of the doctor for the patient. */
function completedVisit(Doctor $doctor, Patient $patient, string $time = '09:00'): string
{
    actAs(staff(RoleName::Receptionist));
    $id = keyed('/appointments', [
        'patient_id' => $patient->id, 'provider_id' => $doctor->id, 'type' => 'consultation',
        'scheduled_date' => '2026-11-01', 'start_time' => $time, 'source' => 'walk_in',
    ])->assertCreated()->json('data.id');
    test()->patchJson("/appointments/{$id}/check-in")->assertOk();
    actAs(User::query()->findOrFail($doctor->id));
    test()->patchJson("/appointments/{$id}/start")->assertOk();
    test()->patchJson("/appointments/{$id}/complete", [])->assertOk();

    return $id;
}

/** Issued invoice of one completed consultation (total 15.000). */
function issuedInvoice(): array
{
    $patient = FrontDesk::patient();
    completedVisit(FrontDesk::doctor(), $patient);
    actAs(staff(RoleName::Receptionist));
    $draft = test()->getJson("/billing/invoices?filter[patient_id][eq]={$patient->id}")->json('data.0');

    return keyed("/billing/invoices/{$draft['id']}/issue")->assertOk()->json('data');
}

it('charges a completed visit onto the patient draft, edits it within the discount limit and issues it with revenue posted', function () {
    $patient = FrontDesk::patient();
    $visit = completedVisit(FrontDesk::doctor(), $patient);
    actAs(staff(RoleName::Receptionist));

    $draft = $this->getJson("/billing/invoices?filter[patient_id][eq]={$patient->id}&filter[status][eq]=draft")
        ->assertOk()->assertJsonCount(1, 'data')->json('data.0');
    expect($draft['total']['amount'])->toBe('15.000');
    $this->getJson('/billing/invoices?search='.urlencode($patient->person->last_name))->assertJsonPath('data.0.id', $draft['id']);
    $line = $this->getJson("/billing/invoices/{$draft['id']}")
        ->assertJsonPath('data.items.0.source.type', 'appointment')
        ->assertJsonPath('data.items.0.source.id', $visit)
        ->json('data.items.0');

    // A second charge of the same visit is refused; billing at check-in is the other way in.
    $this->postJson('/billing/invoices/charge', ['source_type' => 'appointment', 'source_id' => $visit])
        ->assertStatus(409)->assertJsonPath('error_code', 'ALREADY_CHARGED');

    $lines = fn (string $discount) => ['version' => $draft['version'], 'items' => [
        ['id' => $line['id'], 'quantity' => '1', 'discount_type' => 'percentage', 'discount_value' => $discount],
        ['description' => 'Dressing', 'quantity' => '2', 'unit_price' => '1.500'],
    ]];
    $this->putJson("/billing/invoices/{$draft['id']}", $lines('25'))
        ->assertForbidden()->assertJsonPath('error_code', 'DISCOUNT_LIMIT_EXCEEDED')->assertJsonPath('meta.limit_percent', '10');
    $draft = $this->putJson("/billing/invoices/{$draft['id']}", $lines('10'))->assertOk()
        ->assertJsonPath('data.discount_total.amount', '1.500')
        ->assertJsonPath('data.total.amount', '16.500')
        ->assertJsonCount(2, 'data.items')
        ->json('data');

    $issued = keyed("/billing/invoices/{$draft['id']}/issue")->assertOk()
        ->assertJsonPath('data.status', 'issued')
        ->assertJsonPath('data.balance_due.amount', '16.500')
        ->json('data');
    expect($issued['invoice_number'])->toStartWith('INV-')
        ->and(JournalEntry::query()->findOrFail($issued['journal_entry_id'])->status->value)->toBe('posted');

    $this->putJson("/billing/invoices/{$draft['id']}", $lines('0'))->assertStatus(409)->assertJsonPath('error_code', 'INVOICE_NOT_EDITABLE');
});

it('bills a visit at check-in so the patient pays before the consultation, and completion does not bill it again', function () {
    $doctor = FrontDesk::doctor();
    $patient = FrontDesk::patient();
    actAs(staff(RoleName::Receptionist));
    $id = keyed('/appointments', [
        'patient_id' => $patient->id, 'provider_id' => $doctor->id, 'type' => 'consultation',
        'scheduled_date' => '2026-11-01', 'start_time' => '10:00', 'source' => 'walk_in',
    ])->json('data.id');

    $this->postJson('/billing/invoices/charge', ['source_type' => 'appointment', 'source_id' => $id])
        ->assertUnprocessable()->assertJsonPath('error_code', 'NOT_CHARGEABLE');
    $this->travelTo(CarbonImmutable::parse('2026-11-01 09:30', 'Asia/Bahrain'));
    $this->patchJson("/appointments/{$id}/check-in")->assertOk();
    $invoice = $this->postJson('/billing/invoices/charge', ['source_type' => 'appointment', 'source_id' => $id])
        ->assertOk()->assertJsonPath('data.total.amount', '15.000')->json('data');

    actAs(User::query()->findOrFail($doctor->id));
    $this->patchJson("/appointments/{$id}/start")->assertOk();
    $this->patchJson("/appointments/{$id}/complete", [])->assertOk();

    actAs(staff(RoleName::Receptionist));
    $this->getJson("/billing/invoices/{$invoice['id']}")->assertJsonCount(1, 'data.items');
    $this->getJson("/appointments/{$id}")->assertJsonPath('data.invoice.id', $invoice['id'])->assertJsonPath('data.invoice.status', 'draft');
});

it('takes cash only into an open drawer, splits a payment across methods and replays a retried request', function () {
    $invoice = issuedInvoice();
    $cashier = actAs(staff(RoleName::Cashier));

    $cash = ['invoice_id' => $invoice['id'], 'amount' => '10.000', 'method' => 'cash'];
    keyed('/billing/payments', $cash)->assertStatus(409)->assertJsonPath('error_code', 'NO_OPEN_CASH_SESSION');

    $this->getJson('/finance/cash-sessions/current')->assertOk()->assertJsonPath('data', null);
    $session = $this->postJson('/finance/cash-sessions', ['opening_float' => '20.000'])->assertCreated()->json('data');
    $this->postJson('/finance/cash-sessions', ['opening_float' => '20.000'])->assertStatus(409)->assertJsonPath('error_code', 'CASH_SESSION_ALREADY_OPEN');

    $key = (string) Str::uuid();
    $payment = keyed('/billing/payments', $cash, $key)->assertCreated()
        ->assertJsonPath('data.cash_session_id', $session['id'])
        ->assertJsonPath('data.invoice.balance_due.amount', '5.000')
        ->json('data');
    keyed('/billing/payments', $cash, $key)->assertCreated()->assertJsonPath('data.id', $payment['id']);

    keyed('/billing/payments', ['invoice_id' => $invoice['id'], 'amount' => '6.000', 'method' => 'card'])
        ->assertUnprocessable()->assertJsonPath('error_code', 'PAYMENT_EXCEEDS_BALANCE');
    keyed('/billing/payments', ['invoice_id' => $invoice['id'], 'amount' => '5.000', 'method' => 'card', 'card_last4' => '4242'])->assertCreated();

    $this->getJson("/billing/invoices/{$invoice['id']}")
        ->assertJsonPath('data.status', 'paid')->assertJsonPath('data.balance_due.amount', '0.000')->assertJsonCount(2, 'data.payments');
    $this->getJson('/finance/cash-sessions/current')
        ->assertJsonPath('data.report.cash_in.amount', '10.000')
        ->assertJsonPath('data.report.expected.amount', '30.000')
        ->assertJsonPath('data.report.by_method', [['method' => 'card', 'total' => ['amount' => '5.000', 'currency' => 'BHD']], ['method' => 'cash', 'total' => ['amount' => '10.000', 'currency' => 'BHD']]]);
    $this->getJson('/billing/payments/daily-summary')->assertOk()
        ->assertJsonPath('data.grand_total', '15.000')->assertJsonPath('data.rows.0.received_by', $cashier->id);
});

it('voids a same-day payment back onto the balance and refunds through request, approval by someone else and pay-out', function () {
    $invoice = issuedInvoice();
    $cashier = actAs(staff(RoleName::Cashier));
    $this->postJson('/finance/cash-sessions', ['opening_float' => '0'])->assertCreated();
    $mistake = keyed('/billing/payments', ['invoice_id' => $invoice['id'], 'amount' => '15.000', 'method' => 'cash'])->json('data');

    actAs(staff(RoleName::Accountant));
    $this->postJson("/billing/payments/{$mistake['id']}/void", ['reason' => 'Wrong patient'])->assertOk()->assertJsonPath('data.status', 'voided');
    $this->getJson("/billing/invoices/{$invoice['id']}")->assertJsonPath('data.status', 'issued')->assertJsonPath('data.balance_due.amount', '15.000');

    actAs($cashier);
    $payment = keyed('/billing/payments', ['invoice_id' => $invoice['id'], 'amount' => '15.000', 'method' => 'cash'])->json('data');
    $this->postJson('/billing/refunds', ['payment_id' => $payment['id'], 'amount' => '16.000', 'reason' => 'Overcharged'])
        ->assertUnprocessable()->assertJsonPath('error_code', 'REFUND_EXCEEDS_PAID');
    $refund = $this->postJson('/billing/refunds', ['payment_id' => $payment['id'], 'amount' => '5.000', 'reason' => 'Dressing not used'])
        ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.method', 'cash')->json('data');
    $this->postJson("/billing/refunds/{$refund['id']}/approve")->assertForbidden();

    actAs(staff(RoleName::Accountant));
    $this->postJson("/billing/refunds/{$refund['id']}/approve")->assertOk()->assertJsonPath('data.status', 'approved');

    actAs($cashier);
    keyed("/billing/refunds/{$refund['id']}/process")->assertOk()->assertJsonPath('data.is_processed', true);
    $this->getJson("/billing/invoices/{$invoice['id']}")->assertJsonPath('data.refunded_amount.amount', '5.000');
    $this->getJson("/billing/payments/{$payment['id']}")->assertJsonPath('data.refundable.amount', '10.000');
    $this->getJson('/finance/cash-sessions/current')->assertJsonPath('data.report.expected.amount', '10.000');
});

it('closes a drawer, leaves a large difference for a supervisor and deposits the counted cash', function () {
    $invoice = issuedInvoice();
    $cashier = actAs(staff(RoleName::Cashier));
    $session = $this->postJson('/finance/cash-sessions', ['opening_float' => '20.000'])->json('data');
    keyed('/billing/payments', ['invoice_id' => $invoice['id'], 'amount' => '15.000', 'method' => 'cash'])->assertCreated();

    $closed = $this->postJson("/finance/cash-sessions/{$session['id']}/close", ['counted_closing' => '30.000', 'denominations' => [['value' => '20', 'count' => 1], ['value' => '10', 'count' => 1], ['value' => '5', 'count' => 0]]])
        ->assertOk()
        ->assertJsonPath('data.status', 'closing')
        ->assertJsonPath('data.expected_closing.amount', '35.000')
        ->assertJsonPath('data.difference.amount', '-5.000')
        ->json('data');
    expect($closed['denominations'])->toBe([['value' => '20.000', 'count' => 1], ['value' => '10.000', 'count' => 1]]);
    $this->postJson("/finance/cash-sessions/{$session['id']}/approve-difference", ['notes' => 'Counted twice'])->assertForbidden();

    actAs(staff(RoleName::Accountant));
    $this->postJson("/finance/cash-sessions/{$session['id']}/approve-difference", ['notes' => 'Short 5 BHD, cashier warned'])
        ->assertOk()->assertJsonPath('data.status', 'closed');
    keyed("/finance/cash-sessions/{$session['id']}/deposit", ['date' => '2026-11-01', 'reference' => 'NBB-123', 'amount' => '31.000'])
        ->assertUnprocessable()->assertJsonPath('error_code', 'DEPOSIT_EXCEEDS_CASH');
    keyed("/finance/cash-sessions/{$session['id']}/deposit", ['date' => '2026-11-01', 'reference' => 'NBB-123', 'amount' => '30.000'])
        ->assertOk()->assertJsonPath('data.deposit_reference', 'NBB-123');

    actAs($cashier);
    $this->getJson('/finance/cash-sessions')->assertOk()->assertJsonCount(1, 'data');
});

it('cancels an issued invoice without payments by reversing its revenue', function () {
    $invoice = issuedInvoice();
    actAs(staff(RoleName::Accountant));

    $this->postJson("/billing/invoices/{$invoice['id']}/cancel", ['reason' => 'Billed to the wrong file'])
        ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.balance_due.amount', '0.000');
    expect(JournalEntry::query()->findOrFail($invoice['journal_entry_id'])->status->value)->toBe('reversed');
});

it('runs an expense from draft to approval by someone else and pays it from the drawer', function () {
    $clerk = actAs(staff(RoleName::Accountant));
    $this->postJson('/finance/cash-sessions', ['opening_float' => '50.000'])->assertForbidden();

    $expense = $this->postJson('/finance/expenses', [
        'category' => 'office_supplies', 'payee' => 'Stationery shop', 'description' => 'Printer paper',
        'amount' => '11.000', 'tax_amount' => '1.000', 'expense_date' => '2026-11-01',
    ])->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.expense_account.code', '5500')->json('data');
    $this->postJson("/finance/expenses/{$expense['id']}/submit")->assertOk()->assertJsonPath('data.status', 'pending');
    $this->postJson("/finance/expenses/{$expense['id']}/approve")->assertForbidden()->assertJsonPath('error_code', 'SELF_APPROVAL_NOT_ALLOWED');

    actAs(staff(RoleName::Admin));
    $this->postJson("/finance/expenses/{$expense['id']}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    keyed("/finance/expenses/{$expense['id']}/pay", ['method' => 'bank_transfer'])->assertUnprocessable();
    keyed("/finance/expenses/{$expense['id']}/pay", ['method' => 'bank_transfer', 'reference' => 'TRX-9'])
        ->assertOk()->assertJsonPath('data.is_paid', true)->assertJsonPath('data.payment_method', 'bank_transfer');

    actAs($clerk);
    $this->getJson("/finance/expenses/{$expense['id']}")->assertOk()->assertJsonPath('data.submitted_by.id', $clerk->id);
});

it('claims the insurer share of an invoice, records the approval as a payment and clears it with the remittance', function () {
    $insurer = Company::query()->create(['type' => CompanyType::Insurer, 'name' => 'Gulf Insurance Group', 'country_code' => 'BH']);
    $patient = FrontDesk::patient();
    $patient->forceFill([
        'insurance_company_id' => $insurer->id, 'insurance_policy_number' => 'GIG-7781',
        'insurance_expiry_date' => '2027-06-30', 'insurance_coverage_percent' => '80',
    ])->save();
    $visit = completedVisit(FrontDesk::doctor(), $patient);
    actAs(staff(RoleName::Receptionist));
    $draft = $this->getJson("/billing/invoices?filter[patient_id][eq]={$patient->id}")->json('data.0');
    $invoice = keyed("/billing/invoices/{$draft['id']}/issue")->json('data');

    actAs(staff(RoleName::Accountant));
    keyed('/billing/insurance-claims', ['invoice_id' => $invoice['id']])
        ->assertUnprocessable()->assertJsonPath('error_code', 'CLAIM_MISSING_DIAGNOSIS');

    $icd = Icd10Code::query()->create(['code' => 'J02.9', 'title' => 'Acute pharyngitis, unspecified']);
    MedicalRecord::query()->create(['id' => $visit])->forceFill(['signed_at' => now(), 'signed_by' => null])->save();
    RecordDiagnosis::query()->create(['medical_record_id' => $visit, 'icd10_code_id' => $icd->id, 'is_primary' => true]);

    $claim = keyed('/billing/insurance-claims', ['invoice_id' => $invoice['id'], 'pre_authorization_number' => 'PA-1'])
        ->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.claimed_amount.amount', '12.000')->json('data');
    keyed('/billing/insurance-claims', ['invoice_id' => $invoice['id']])->assertStatus(409)->assertJsonPath('error_code', 'CLAIM_ALREADY_EXISTS');
    $this->getJson("/billing/insurance-claims/{$claim['id']}")->assertJsonPath('data.diagnoses.0.code', 'J02.9');

    $this->patchJson("/billing/insurance-claims/{$claim['id']}/submit")->assertOk()->assertJsonPath('data.status', 'pending');
    $this->patchJson("/billing/insurance-claims/{$claim['id']}/decision", ['approved' => true, 'approved_amount' => '10.000', 'insurer_reference' => 'GIG-A-55'])
        ->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.approved_amount.amount', '10.000');
    $this->getJson("/billing/invoices/{$invoice['id']}")
        ->assertJsonPath('data.balance_due.amount', '5.000')->assertJsonPath('data.payments.0.method', 'insurance');

    keyed('/billing/insurance-claims/remittances', ['claim_ids' => [$claim['id']], 'date' => '2026-11-01', 'reference' => 'BNK-1', 'amount' => '9.000'])
        ->assertUnprocessable()->assertJsonPath('error_code', 'CLAIM_REMITTANCE_MISMATCH');
    keyed('/billing/insurance-claims/remittances', ['claim_ids' => [$claim['id']], 'date' => '2026-11-01', 'reference' => 'BNK-1', 'amount' => '10.000'])
        ->assertOk()->assertJsonPath('data.0.remitted_at', '2026-11-01');
});

it('shows how an invoice stands with its payments and filters by state and by how it was paid', function () {
    $invoice = issuedInvoice();
    actAs(staff(RoleName::Cashier));
    $this->postJson('/finance/cash-sessions', ['opening_float' => '20.000'])->assertCreated();
    $states = fn (string $query) => collect($this->getJson("/billing/invoices?{$query}")->assertOk()->json('data'))->pluck('id')->all();

    $this->getJson("/billing/invoices/{$invoice['id']}")->assertJsonPath('data.payment_state', 'unpaid');
    expect($states('payment_state=unpaid'))->toBe([$invoice['id']]);

    keyed('/billing/payments', ['invoice_id' => $invoice['id'], 'amount' => '10.000', 'method' => 'cash'])->assertCreated();
    $this->getJson("/billing/invoices/{$invoice['id']}")->assertJsonPath('data.payment_state', 'partially_paid');
    expect($states('payment_state=partially_paid'))->toBe([$invoice['id']])
        ->and($states('method=cash'))->toBe([$invoice['id']])
        ->and($states('method=card'))->toBe([]);

    keyed('/billing/payments', ['invoice_id' => $invoice['id'], 'amount' => '5.000', 'method' => 'card', 'card_last4' => '4242'])->assertCreated();
    $row = collect($this->getJson('/billing/invoices?payment_state=paid')->json('data'))->firstWhere('id', $invoice['id']);
    expect($row['payment_state'])->toBe('paid')
        ->and($row['payment_methods'])->toBe(['cash', 'card'])
        ->and($states('method=card'))->toBe([$invoice['id']])
        ->and($states('payment_state=unpaid'))->toBe([]);

    // Unpaid after its due date reads as overdue.
    $second = issuedInvoice();
    actAs(staff(RoleName::Cashier));
    $this->travel(40)->days();
    expect($states('payment_state=overdue'))->toContain($second['id']);
    $this->getJson('/billing/invoices?payment_state=later')->assertStatus(422);
    $this->getJson('/billing/invoices?method=coins')->assertStatus(422);
});
