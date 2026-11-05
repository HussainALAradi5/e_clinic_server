<?php

use App\Enums\Appointments\AppointmentStatus;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Enums\Reference\CompanyType;
use App\Interfaces\Pharmacy\InventoryServiceInterface;
use App\Models\Accounting\JournalEntry;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\Patient;
use App\Models\HR\Employee;
use App\Models\Identity\Notification as AppNotification;
use App\Models\Identity\User;
use App\Models\Payments\BillableService;
use App\Models\Payments\Invoice;
use App\Models\Pharmacy\MedicationBatch;
use App\Models\Pharmacy\StockMovement;
use App\Models\Reference\Company;
use App\Models\Transactions\Expense;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FiscalYearSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FrontDesk;

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->seed([CountrySeeder::class, RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);
    Notification::fake();
    // Sunday 1 Nov 2026, 08:00 in Bahrain.
    $this->travelTo(CarbonImmutable::parse('2026-11-01 05:00:00', 'UTC'));
    $this->seed(FiscalYearSeeder::class);
});

function pharmacist(): Employee
{
    $employee = FrontDesk::employee(UserType::Pharmacist, RoleName::Pharmacist);
    Sanctum::actingAs(User::query()->findOrFail($employee->id));

    return $employee;
}

function asUser(string $id): void
{
    Sanctum::actingAs(User::query()->findOrFail($id));
}

function idempotent(string $url, array $payload): TestResponse
{
    return test()->postJson($url, $payload, ['Idempotency-Key' => (string) Str::uuid()]);
}

/** @return array<string, mixed> */
function medicationPayload(string $code, array $overrides = []): array
{
    return [
        'code' => $code, 'generic_name' => 'Amoxicillin', 'brand_name' => 'Amoxil', 'form' => 'capsule', 'strength' => '500 mg',
        'unit' => 'capsule', 'price' => '0.250', 'tax_rate' => '0', 'reorder_level' => 50, ...$overrides,
    ];
}

function medication(string $code = 'MED-AMOX-500', array $overrides = []): array
{
    return test()->postJson('/pharmacy/medications', medicationPayload($code, $overrides))->assertCreated()->json('data');
}

function receive(string $medicationId, string $lot, string $expiry, string $quantity, string $cost = '0.100', array $extra = []): array
{
    return idempotent('/pharmacy/inventory/receipts', [
        'medication_id' => $medicationId, 'batch_number' => $lot, 'expiry_date' => $expiry, 'quantity' => $quantity, 'unit_cost' => $cost, ...$extra,
    ])->assertCreated()->json('data');
}

function prescribe(Patient $patient, array $items, array $extra = []): TestResponse
{
    return test()->postJson('/pharmacy/prescriptions', ['patient_id' => $patient->id, 'items' => $items, ...$extra]);
}

/** @return array<string, mixed> */
function rxLine(string $medicationId, string $quantity = '21'): array
{
    return ['medication_id' => $medicationId, 'dose' => '1 capsule', 'frequency' => '3 times daily', 'route' => 'oral', 'duration_days' => 7, 'quantity' => $quantity, 'instructions' => 'After meals'];
}

it('keeps a medication priced through its own price-list service and guards its history', function () {
    pharmacist();
    $amox = medication();
    expect($amox['price']['amount'])->toBe('0.250')->and($amox['stock_on_hand'])->toBe('0.00')->and($amox['name'])->toBe('Amoxicillin 500 mg Capsule (Amoxil)');
    $service = BillableService::query()->findOrFail($amox['billable_service_id']);
    expect($service->code)->toBe('MED-AMOX-500')->and($service->category->value)->toBe('pharmacy');

    // The service code is the medication code: a taken one is refused.
    FrontDesk::service('MED-PARA-500');
    $this->postJson('/pharmacy/medications', medicationPayload('MED-PARA-500', ['generic_name' => 'Paracetamol']))
        ->assertStatus(409)->assertJsonPath('error_code', 'SERVICE_CODE_TAKEN');

    $this->putJson("/pharmacy/medications/{$amox['id']}", ['price' => '0.300', 'gtin' => '05012345678900'])->assertOk()
        ->assertJsonPath('data.price.amount', '0.300');
    expect((string) $service->refresh()->price->getAmount())->toBe('0.300');
    $this->getJson('/pharmacy/medications/barcode/05012345678900')->assertOk()->assertJsonPath('data.id', $amox['id']);
    $this->getJson('/pharmacy/medications?search=amoxil')->assertOk()->assertJsonPath('data.0.id', $amox['id']);

    // With stock history it can only be archived — and its price-list line goes with it.
    receive($amox['id'], 'A1', '2027-06-30', '10');
    $this->deleteJson("/pharmacy/medications/{$amox['id']}")->assertStatus(409)->assertJsonPath('error_code', 'INVALID_STATE_TRANSITION');
    $this->patchJson("/pharmacy/medications/{$amox['id']}/archive")->assertOk()->assertJsonPath('data.is_archived', true);
    expect($service->refresh()->is_archived)->toBeTrue();
});

it('receives lots (topping up the same lot at average cost), books the purchase and flags low stock', function () {
    pharmacist();
    $amox = medication();
    $supplier = Company::query()->create(['type' => CompanyType::Supplier, 'name' => 'Gulf Medical Supplies', 'country_code' => 'BH']);

    $lot = receive($amox['id'], 'A1', '2027-06-30', '30', '0.100', ['supplier_id' => $supplier->id, 'create_expense' => true, 'vendor_invoice_number' => 'GMS-881']);
    expect($lot['qty_on_hand'])->toBe('30.00')->and($lot['expiry_status'])->toBe('ok')->and($lot['code'])->toStartWith('LOT-');
    $expense = Expense::query()->findOrFail($lot['expense']['id']);
    expect($expense->status->value)->toBe('pending')
        ->and($expense->category->value)->toBe('pharmacy_purchase')
        ->and((string) $expense->amount->getAmount())->toBe('3.000')
        ->and($expense->expenseAccount->code)->toBe('1400');

    // Same lot again: one lot, weighted cost (30 × 0.100 + 10 × 0.200) / 40 = 0.125.
    $topped = receive($amox['id'], 'A1', '2027-06-30', '10', '0.200');
    expect($topped['id'])->toBe($lot['id'])->and($topped['qty_on_hand'])->toBe('40.00')->and($topped['unit_cost']['amount'])->toBe('0.125');
    idempotent('/pharmacy/inventory/receipts', ['medication_id' => $amox['id'], 'batch_number' => 'A1', 'expiry_date' => '2027-07-31', 'quantity' => '1', 'unit_cost' => '0.1'])
        ->assertUnprocessable()->assertJsonValidationErrors('expiry_date');

    // 40 on hand, reorder level 50 → low.
    $this->getJson('/pharmacy/medications/low-stock')->assertOk()->assertJsonPath('data.0.id', $amox['id'])->assertJsonPath('data.0.is_low_stock', true);
    $this->getJson('/pharmacy/inventory/movements?filter[medication_id][eq]='.$amox['id'])->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.type', 'receipt');
});

it('prescribes with duplicate and allergy checks, dispenses FEFO across lots, bills it and posts the cost', function () {
    $patient = FrontDesk::patient();
    $patient->forceFill(['allergies' => 'Penicillin, amoxicillin (rash)'])->save();
    $doctor = FrontDesk::doctor();
    $pharmacist = pharmacist();
    $amox = medication();
    $para = medication('MED-PARA-500', ['generic_name' => 'Paracetamol', 'brand_name' => 'Panadol', 'form' => 'tablet', 'unit' => 'tablet', 'price' => '0.050', 'requires_prescription' => false]);
    receive($amox['id'], 'LATE', '2027-12-31', '50', '0.120');
    receive($amox['id'], 'EARLY', '2027-03-31', '15', '0.100');
    receive($para['id'], 'P1', '2027-12-31', '100', '0.020');

    asUser($doctor->id);
    prescribe($patient, [rxLine($amox['id'])])->assertStatus(409)->assertJsonPath('error_code', 'ALLERGY_WARNING');
    $rx = prescribe($patient, [rxLine($amox['id']), rxLine($para['id'], '10')], ['override_allergy' => true])->assertCreated()
        ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.doctor_id', $doctor->id)->json('data');
    expect($rx['code'])->toStartWith('RX-')->and($rx['valid_until'])->toBe('2026-12-01');
    prescribe($patient, [rxLine($amox['id'])], ['override_allergy' => true])->assertStatus(409)->assertJsonPath('error_code', 'DUPLICATE_THERAPY');

    asUser($pharmacist->id);
    $this->getJson('/pharmacy/prescriptions/queue')->assertOk()->assertJsonPath('data.0.id', $rx['id']);
    [$amoxLine, $paraLine] = $rx['items'];

    // Partial: 20 capsules — the 15 of the earlier expiry first, then 5 of the later lot.
    $first = idempotent('/pharmacy/dispensations', ['patient_id' => $patient->id, 'prescription_id' => $rx['id'], 'items' => [
        ['medication_id' => $amox['id'], 'quantity' => '20'],
    ]])->assertCreated()->assertJsonPath('data.kind', 'take_home')->json('data');
    expect(collect($first['items'])->map(fn ($i) => [$i['batch']['batch_number'], $i['quantity']])->all())->toBe([['EARLY', '15.00'], ['LATE', '5.00']])
        ->and($first['invoice']['status'])->toBe('draft');
    $cogs = JournalEntry::query()->findOrFail($first['journal_entry_id']);
    expect((string) $cogs->total_debit)->toBe('2.100'); // 15 × 0.100 + 5 × 0.120

    $this->getJson("/pharmacy/prescriptions/{$rx['id']}")->assertOk()->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.items.0.dispensed_quantity', '20.00')->assertJsonPath('data.items.0.remaining_quantity', '1.00');

    idempotent('/pharmacy/dispensations', ['patient_id' => $patient->id, 'prescription_id' => $rx['id'], 'items' => [
        ['medication_id' => $amox['id'], 'quantity' => '2'],
    ]])->assertUnprocessable()->assertJsonPath('error_code', 'DISPENSE_QUANTITY_EXCEEDED')->assertJsonPath('meta.remaining', '1.00');

    idempotent('/pharmacy/dispensations', ['patient_id' => $patient->id, 'prescription_id' => $rx['id'], 'items' => [
        ['prescription_item_id' => $amoxLine['id'], 'medication_id' => $amox['id'], 'quantity' => '1'],
        ['prescription_item_id' => $paraLine['id'], 'medication_id' => $para['id'], 'quantity' => '10'],
    ]])->assertCreated();
    $this->getJson("/pharmacy/prescriptions/{$rx['id']}")->assertJsonPath('data.status', 'completed');
    idempotent('/pharmacy/dispensations', ['patient_id' => $patient->id, 'prescription_id' => $rx['id'], 'items' => [
        ['medication_id' => $para['id'], 'quantity' => '1'],
    ]])->assertStatus(409)->assertJsonPath('error_code', 'PRESCRIPTION_NOT_DISPENSABLE');

    // Everything is on the patient's one draft: 21 × 0.250 + 10 × 0.050.
    $draft = Invoice::query()->where('patient_id', $patient->id)->where('status', 'draft')->sole();
    expect((string) $draft->total->getAmount())->toBe('5.750')->and($draft->items()->count())->toBe(3);
    expect(MedicationBatch::query()->where('batch_number', 'EARLY')->value('qty_on_hand'))->toBe('0.00');
});

it('sells over the counter only what needs no prescription, and controlled drugs need a witness and a prescription', function () {
    $patient = FrontDesk::patient();
    $pharmacist = pharmacist();
    $colleague = FrontDesk::employee(UserType::Pharmacist, RoleName::Pharmacist);
    $amox = medication();
    $para = medication('MED-PARA-500', ['generic_name' => 'Paracetamol', 'form' => 'tablet', 'unit' => 'tablet', 'requires_prescription' => false]);
    $morph = medication('MED-MORPH-10', ['generic_name' => 'Morphine', 'brand_name' => null, 'form' => 'injection', 'unit' => 'ampoule', 'strength' => '10 mg/ml', 'is_controlled' => true]);
    foreach ([$amox, $para, $morph] as $m) {
        receive($m['id'], 'L1', '2027-12-31', '20');
    }

    idempotent('/pharmacy/dispensations', ['patient_id' => $patient->id, 'items' => [['medication_id' => $amox['id'], 'quantity' => '1']]])
        ->assertUnprocessable()->assertJsonPath('error_code', 'MEDICATION_REQUIRES_PRESCRIPTION');
    idempotent('/pharmacy/dispensations', ['patient_id' => $patient->id, 'items' => [['medication_id' => $para['id'], 'quantity' => '12']]])
        ->assertCreated()->assertJsonPath('data.prescription_id', null);

    asUser(FrontDesk::doctor()->id);
    $rx = prescribe($patient, [rxLine($morph['id'], '2')])->assertCreated()->json('data');
    asUser($pharmacist->id);
    $controlled = ['patient_id' => $patient->id, 'prescription_id' => $rx['id'], 'items' => [['medication_id' => $morph['id'], 'quantity' => '2']]];
    idempotent('/pharmacy/dispensations', $controlled)->assertForbidden()->assertJsonPath('error_code', 'CONTROLLED_DRUG_RULE');
    idempotent('/pharmacy/dispensations', [...$controlled, 'witness_id' => $pharmacist->id])->assertForbidden();
    $this->getJson('/pharmacy/colleagues?right=dispense')->assertOk()->assertJsonPath('data.0.id', $colleague->id)->assertJsonCount(1, 'data');
    idempotent('/pharmacy/dispensations', [...$controlled, 'witness_id' => $colleague->id])->assertCreated()->assertJsonPath('data.witness.id', $colleague->id);
});

it('records medicines given in a session on the appointment, and returns a same-day hand-over to stock', function () {
    $patient = FrontDesk::patient();
    $doctor = FrontDesk::doctor();
    $pharmacist = pharmacist();
    $ceft = medication('MED-CEFT-1G', ['generic_name' => 'Ceftriaxone', 'brand_name' => null, 'form' => 'injection', 'unit' => 'vial', 'strength' => '1 g', 'price' => '3.500']);
    $lot = receive($ceft['id'], 'C1', '2027-12-31', '10', '1.200');
    // Doctors prescribe; the nurse gives the dose in their own treatment-room session.
    $nurse = FrontDesk::employee(UserType::MedicalStaff, RoleName::Nurse);
    $visit = Appointment::query()->create([
        'patient_id' => $patient->id, 'provider_id' => $nurse->id, 'type' => 'consultation', 'scheduled_date' => '2026-11-01',
        'start_time' => '09:00:00', 'end_time' => '09:15:00', 'status' => AppointmentStatus::Confirmed, 'source' => 'walk_in',
    ]);
    $given = ['items' => [['medication_id' => $ceft['id'], 'quantity' => '1', 'route' => 'intramuscular', 'dose' => '1 g']], 'notes' => 'Left gluteal'];

    asUser($doctor->id);
    idempotent("/appointments/{$visit->id}/medications", $given)->assertForbidden();
    asUser($nurse->id);
    idempotent("/appointments/{$visit->id}/medications", $given)->assertUnprocessable()->assertJsonPath('error_code', 'ADMINISTRATION_REQUIRES_SESSION');
    $visit->forceFill(['status' => AppointmentStatus::InProgress])->save();
    // Given in a session, medicines come from the treatment room's stock unless the pharmacy is chosen.
    idempotent("/appointments/{$visit->id}/medications", $given)->assertStatus(409)
        ->assertJsonPath('error_code', 'INSUFFICIENT_STOCK')->assertJsonPath('message', 'Not enough Ceftriaxone 1 g Injection in the treatment room (0.00 available).');
    $dose = idempotent("/appointments/{$visit->id}/medications", [...$given, 'from' => 'pharmacy'])->assertCreated()
        ->assertJsonPath('data.kind', 'administered')->assertJsonPath('data.items.0.route', 'intramuscular')->json('data');
    $this->getJson("/appointments/{$visit->id}/medications")->assertOk()->assertJsonPath('data.dispensations.0.id', $dose['id']);

    // The pharmacy moves four vials to the treatment room: the same batch and expiry there, both lots in the ledger.
    asUser($pharmacist->id);
    $this->postJson("/pharmacy/inventory/batches/{$lot['id']}/transfer", ['quantity' => '4', 'to' => 'pharmacy'])->assertUnprocessable();
    $this->postJson("/pharmacy/inventory/batches/{$lot['id']}/transfer", ['quantity' => '40', 'to' => 'treatment_room'])->assertStatus(409);
    $ward = $this->postJson("/pharmacy/inventory/batches/{$lot['id']}/transfer", ['quantity' => '4', 'to' => 'treatment_room'])->assertOk()
        ->assertJsonPath('data.location', 'treatment_room')->assertJsonPath('data.batch_number', 'C1')
        ->assertJsonPath('data.expiry_date', '2027-12-31')->assertJsonPath('data.qty_on_hand', '4.00')->json('data');
    $this->getJson('/pharmacy/inventory/stock?filter[location][eq]=treatment_room')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/pharmacy/medications/{$ceft['id']}")->assertOk()
        ->assertJsonPath('data.stock_on_hand', '9.00')->assertJsonPath('data.stock_treatment_room', '4.00')->assertJsonPath('data.next_expiry', '2027-12-31');
    asUser($nurse->id);
    idempotent("/appointments/{$visit->id}/medications", $given)->assertCreated();
    expect(MedicationBatch::query()->findOrFail($ward['id'])->qty_on_hand)->toBe('3.00');

    // A take-home sale returned unopened the same day: stock, draft lines and cost entry are reversed.
    asUser($pharmacist->id);
    $sale = idempotent('/pharmacy/dispensations', ['patient_id' => $patient->id, 'items' => [['medication_id' => $ceft['id'], 'quantity' => '2']]]);
    $sale->assertUnprocessable(); // prescription-only
    $otc = medication('MED-ORS', ['generic_name' => 'Oral rehydration salts', 'brand_name' => null, 'form' => 'other', 'unit' => 'pack', 'strength' => '20.5 g', 'price' => '0.400', 'requires_prescription' => false]);
    receive($otc['id'], 'O1', '2027-12-31', '5', '0.150');
    $sale = idempotent('/pharmacy/dispensations', ['patient_id' => $patient->id, 'items' => [['medication_id' => $otc['id'], 'quantity' => '2']]]);
    $sale = $sale->assertCreated()->json('data');
    $draft = Invoice::query()->where('patient_id', $patient->id)->where('status', 'draft')->sole();
    expect((string) $draft->total->getAmount())->toBe('7.800'); // 2 × 3.500 (two doses) + 2 × 0.400

    $this->patchJson("/pharmacy/dispensations/{$sale['id']}/cancel", ['reason' => 'Returned unopened'])->assertOk()
        ->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.invoice', null);
    expect((string) $draft->refresh()->total->getAmount())->toBe('7.000')
        ->and(MedicationBatch::query()->where('batch_number', 'O1')->value('qty_on_hand'))->toBe('5.00')
        ->and(JournalEntry::query()->findOrFail($sale['journal_entry_id'])->status->value)->toBe('reversed');
    $this->patchJson("/pharmacy/dispensations/{$sale['id']}/cancel", ['reason' => 'Again'])->assertStatus(409);
    expect(MedicationBatch::query()->findOrFail($lot['id'])->qty_on_hand)->toBe('5.00');
});

it('corrects counts (large ones approved by a colleague), blocks quarantined lots and writes off expired stock', function () {
    $patient = FrontDesk::patient();
    $pharmacist = pharmacist();
    $colleague = FrontDesk::employee(UserType::Pharmacist, RoleName::Pharmacist);
    $para = medication('MED-PARA-500', ['generic_name' => 'Paracetamol', 'form' => 'tablet', 'unit' => 'tablet', 'requires_prescription' => false]);
    $lot = receive($para['id'], 'P1', '2026-12-31', '100', '0.020');

    $this->postJson("/pharmacy/inventory/batches/{$lot['id']}/adjust", ['quantity' => '-3', 'reason' => 'Broken strip'])->assertCreated()
        ->assertJsonPath('data.type', 'adjustment')->assertJsonPath('data.quantity', '-3.00');
    $this->postJson("/pharmacy/inventory/batches/{$lot['id']}/adjust", ['quantity' => '-20', 'reason' => 'Count'])
        ->assertForbidden()->assertJsonPath('error_code', 'SELF_APPROVAL_NOT_ALLOWED');
    $this->postJson("/pharmacy/inventory/batches/{$lot['id']}/adjust", ['quantity' => '-20', 'reason' => 'Count', 'approved_by' => $colleague->id])
        ->assertCreated()->assertJsonPath('data.approved_by.id', $colleague->id);
    $this->postJson("/pharmacy/inventory/batches/{$lot['id']}/adjust", ['quantity' => '-100', 'reason' => 'Count', 'approved_by' => $colleague->id])
        ->assertStatus(409)->assertJsonPath('error_code', 'INSUFFICIENT_STOCK');
    expect(JournalEntry::query()->where('source_id', $lot['id'])->count())->toBe(2);

    $this->patchJson("/pharmacy/inventory/batches/{$lot['id']}/quarantine", ['quarantined' => true, 'reason' => 'Supplier recall'])->assertOk()
        ->assertJsonPath('data.is_quarantined', true);
    idempotent('/pharmacy/dispensations', ['patient_id' => $patient->id, 'items' => [['medication_id' => $para['id'], 'quantity' => '1']]])
        ->assertStatus(409)->assertJsonPath('error_code', 'INSUFFICIENT_STOCK');
    idempotent('/pharmacy/dispensations', ['patient_id' => $patient->id, 'items' => [['medication_id' => $para['id'], 'quantity' => '1', 'batch_id' => $lot['id']]]])
        ->assertStatus(409)->assertJsonPath('error_code', 'BATCH_EXPIRED');
    $this->patchJson("/pharmacy/inventory/batches/{$lot['id']}/quarantine", ['quarantined' => false, 'reason' => 'Recall lifted'])->assertOk();

    // The ledger always adds up to what is on hand.
    expect(StockMovement::query()->where('batch_id', $lot['id'])->get()->sum(fn ($m) => (float) $m->quantity))->toBe(77.0);

    $this->travelTo(CarbonImmutable::parse('2027-01-02 05:00:00', 'UTC'));
    $this->seed(FiscalYearSeeder::class);
    expect(app(InventoryServiceInterface::class)->writeOffExpired(CarbonImmutable::parse('2027-01-02')))->toBe(1);
    $this->getJson("/pharmacy/inventory/batches/{$lot['id']}")->assertOk()->assertJsonPath('data.qty_on_hand', '0.00')->assertJsonPath('data.expiry_status', 'expired');
});

it('limits what each role sees: patients their own prescriptions (and are told about them), nurses no pharmacy stock', function () {
    $patient = FrontDesk::patient();
    $other = FrontDesk::patient();
    $portal = User::factory()->state(['id' => $patient->id, 'user_type' => UserType::Patient])->withRole(RoleName::Patient->value)->create();
    pharmacist();
    $para = medication('MED-PARA-500', ['generic_name' => 'Paracetamol', 'form' => 'tablet', 'unit' => 'tablet']);
    asUser(FrontDesk::doctor()->id);
    $mine = prescribe($patient, [rxLine($para['id'], '10')])->assertCreated()->json('data');
    prescribe($other, [rxLine($para['id'], '10')])->assertCreated();

    Sanctum::actingAs($portal);
    $this->getJson('/pharmacy/prescriptions')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine['id']);
    expect(AppNotification::query()->where('person_id', $patient->id)->where('template', 'prescription.created')->where('related_id', $mine['id'])->exists())->toBeTrue();

    asUser(FrontDesk::therapist()->id);
    $this->getJson('/pharmacy/inventory/stock')->assertForbidden();
    $this->getJson('/pharmacy/medications')->assertOk();
});

it('lists, bulk-archives and exports the catalogue and the stock with the shared list tools', function () {
    pharmacist();
    $amox = medication();
    $para = medication('MED-PARA-500', ['generic_name' => 'Paracetamol', 'form' => 'tablet', 'unit' => 'tablet']);
    receive($amox['id'], 'A1', '2027-06-30', '10');

    $this->getJson('/meta/resources/medications')->assertOk()->assertJsonPath('data.importable', true)
        ->assertJsonPath('data.export_formats', ['xlsx', 'pdf', 'csv']);
    $this->getJson('/meta/resources/stock_movements')->assertOk();
    $this->getJson('/pharmacy/medications?sort=-stock_on_hand')->assertOk()->assertJsonPath('data.0.id', $amox['id']);

    $this->postJson('/pharmacy/medications/bulk', ['action' => 'archive', 'ids' => [$para['id']]])->assertOk()->assertJsonPath('data.succeeded', 1);
    $this->getJson('/pharmacy/medications')->assertJsonCount(1, 'data');

    $this->postJson('/pharmacy/medications/export', ['format' => 'csv', 'columns' => ['code', 'name', 'price', 'stock_on_hand']])->assertOk();
    $csv = $this->postJson('/pharmacy/inventory/stock/export', ['format' => 'csv', 'columns' => ['code', 'medication', 'qty_on_hand']])->assertOk()->streamedContent();
    expect($csv)->toContain('Amoxicillin 500 mg Capsule (Amoxil)')->toContain('10.00');
    $this->get('/pharmacy/medications/import/template')->assertOk();
});
