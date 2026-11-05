<?php

use App\Dtos\Accounting\FiscalYearData;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Interfaces\Accounting\FiscalPeriodServiceInterface;
use App\Interfaces\Payments\PackageCoverServiceInterface;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\Patient;
use App\Models\Identity\User;
use App\Models\Payments\PatientPackage;
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

function pkgPost(string $url, array $payload = []): TestResponse
{
    return test()->postJson($url, $payload, ['Idempotency-Key' => (string) Str::uuid()]);
}

function pkgAs(RoleName $role): User
{
    $user = User::factory()->withRole($role->value)->withTwoFactor()->create();
    Sanctum::actingAs($user);

    return $user;
}

/** Books a walk-in for the patient with the doctor at [time]; returns the appointment. */
function pkgVisit(Patient $patient, string $time): Appointment
{
    pkgAs(RoleName::Receptionist);
    $id = pkgPost('/appointments', [
        'patient_id' => $patient->id, 'provider_id' => FrontDesk::doctor()->id, 'type' => 'consultation',
        'scheduled_date' => '2026-11-01', 'start_time' => $time, 'source' => 'walk_in',
    ])->assertCreated()->json('data.id');

    return Appointment::query()->findOrFail($id);
}

/** Checks the visit in, has the doctor see it and complete it. */
function pkgComplete(Appointment $visit): void
{
    pkgAs(RoleName::Receptionist);
    test()->patchJson("/appointments/{$visit->id}/check-in")->assertOk();
    Sanctum::actingAs(User::query()->findOrFail($visit->provider_id));
    test()->patchJson("/appointments/{$visit->id}/start")->assertOk();
    test()->patchJson("/appointments/{$visit->id}/complete", [])->assertOk();
}

/** The accountant puts a package of [sessions] consultations for [price] on sale; returns its id. */
function pkgCatalog(string $serviceId, int $sessions = 3, string $price = '36.000'): string
{
    pkgAs(RoleName::Accountant);

    return test()->postJson('/billing/packages', [
        'name' => 'Consultation pack', 'covered_service_id' => $serviceId, 'sessions' => $sessions, 'price' => $price, 'validity_days' => 30,
    ])->assertCreated()->json('data.id');
}

/** Sells the package to the patient and pays its invoice in full; returns the patient package. */
function pkgSellAndPay(Patient $patient, string $packageId): array
{
    pkgAs(RoleName::Receptionist);
    $sold = pkgPost("/billing/patients/{$patient->id}/packages", ['package_id' => $packageId])->assertCreated()
        ->assertJsonPath('data.status', 'awaiting_payment')->json('data');
    $invoice = pkgPost("/billing/invoices/{$sold['invoice']['id']}/issue")->assertOk()->json('data');
    pkgAs(RoleName::Cashier);
    pkgPost('/billing/payments', ['invoice_id' => $invoice['id'], 'amount' => $invoice['balance_due']['amount'], 'method' => 'card'])->assertCreated();

    return $sold;
}

it('sells a package on the invoice, activates it once paid, then uses sessions instead of billing visits', function () {
    $patient = FrontDesk::patient();
    $first = pkgVisit($patient, '08:30');
    $package = pkgCatalog($first->billable_service_id);

    $sold = pkgSellAndPay($patient, $package);
    pkgAs(RoleName::Receptionist);
    $this->getJson("/billing/patient-packages?filter[patient_id][eq]={$patient->id}")->assertOk()
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.0.sessions_left', 3)
        ->assertJsonPath('data.0.expires_on', '2026-12-01');

    pkgComplete($first);
    pkgAs(RoleName::Receptionist);
    $this->getJson("/billing/patient-packages?filter[patient_id][eq]={$patient->id}")->assertJsonPath('data.0.sessions_used', 1);
    // Only the package's own invoice exists: the visit was not billed.
    $this->getJson("/billing/invoices?filter[patient_id][eq]={$patient->id}")->assertJsonCount(1, 'data');
    $credited = JournalEntryLine::query()->whereHas('account', fn ($q) => $q->where('code', '2510'))->sum('debit');
    expect((string) $credited)->toStartWith('12');

    // At check-in, billing it by hand is refused: the package covers it.
    $second = pkgVisit($patient, '09:00');
    $this->patchJson("/appointments/{$second->id}/check-in")->assertOk();
    $this->postJson('/billing/invoices/charge', ['source_type' => 'appointment', 'source_id' => $second->id])
        ->assertStatus(409)->assertJsonPath('error_code', 'PACKAGE_COVERED');
});

it('keeps unpaid packages inactive, so visits before payment are billed as usual', function () {
    $patient = FrontDesk::patient();
    $visit = pkgVisit($patient, '08:30');
    $package = pkgCatalog($visit->billable_service_id);
    pkgAs(RoleName::Receptionist);
    pkgPost("/billing/patients/{$patient->id}/packages", ['package_id' => $package])->assertCreated();

    pkgComplete($visit);
    pkgAs(RoleName::Receptionist);
    $draft = $this->getJson("/billing/invoices?filter[patient_id][eq]={$patient->id}&filter[status][eq]=draft")->json('data.0.id');
    $this->getJson("/billing/invoices/{$draft}")->assertJsonCount(2, 'data.items');
});

it('splits the price into sessions that add up exactly, and forfeits unused sessions when it expires', function () {
    $patient = FrontDesk::patient();
    $visit = pkgVisit($patient, '08:30');
    $package = pkgCatalog($visit->billable_service_id, 3, '10.000');
    pkgSellAndPay($patient, $package);

    $bought = PatientPackage::query()->firstOrFail();
    expect([(string) $bought->sessionValue(1)->getAmount(), (string) $bought->sessionValue(3)->getAmount()])->toBe(['3.333', '3.334'])
        ->and((string) $bought->remainingValue()->getAmount())->toBe('10.000');

    $this->travel(31)->days();
    expect(app(PackageCoverServiceInterface::class)->expireDue())->toBe(1)
        ->and(PatientPackage::query()->firstOrFail()->status->value)->toBe('expired');
});

it('lets only the catalog managers change packages, refuses packages off sale and shows patients their own', function () {
    $patient = FrontDesk::patient();
    $visit = pkgVisit($patient, '08:30');
    $package = pkgCatalog($visit->billable_service_id);

    pkgAs(RoleName::Receptionist);
    $this->postJson('/billing/packages', ['name' => 'X', 'covered_service_id' => $visit->billable_service_id, 'sessions' => 3, 'price' => '1', 'validity_days' => 30])->assertForbidden();

    pkgAs(RoleName::Accountant);
    $this->putJson("/billing/packages/{$package}", ['name' => 'Consultation pack', 'covered_service_id' => $visit->billable_service_id, 'sessions' => 1, 'price' => '36', 'validity_days' => 30])->assertUnprocessable();
    $this->putJson("/billing/packages/{$package}", ['name' => 'Consultation pack', 'covered_service_id' => $visit->billable_service_id, 'sessions' => 3, 'price' => '36', 'validity_days' => 30, 'is_active' => false])->assertOk();
    pkgAs(RoleName::Receptionist);
    pkgPost("/billing/patients/{$patient->id}/packages", ['package_id' => $package])->assertStatus(409)->assertJsonPath('error_code', 'PACKAGE_NOT_ON_SALE');

    Sanctum::actingAs(User::factory()->state(['id' => $patient->id, 'user_type' => UserType::Patient])->withRole(RoleName::Patient->value)->create());
    $this->getJson('/billing/patient-packages')->assertOk()->assertJsonCount(0, 'data');
});
