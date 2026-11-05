<?php

use App\Dtos\Accounting\FiscalYearData;
use App\Enums\Identity\RoleName;
use App\Interfaces\Accounting\FiscalPeriodServiceInterface;
use App\Models\Appointments\Appointment;
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
    $this->travelTo(CarbonImmutable::parse('2026-11-01 05:00:00', 'UTC'));
    app(FiscalPeriodServiceInterface::class)->createYear(FiscalYearData::calendar(2026));
});

/** An in-progress visit of the patient, with its doctor signed in; returns the visit id. */
function feeDocumentVisit(Patient $patient): string
{
    $doctor = FrontDesk::doctor();
    Sanctum::actingAs(User::factory()->withRole(RoleName::Receptionist->value)->create());
    $visit = test()->postJson('/appointments', [
        'patient_id' => $patient->id, 'provider_id' => $doctor->id, 'type' => 'consultation',
        'scheduled_date' => '2026-11-01', 'start_time' => '11:00', 'source' => 'phone',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.id');
    Appointment::query()->whereKey($visit)->update(['status' => 'in_progress']);
    Sanctum::actingAs(User::query()->findOrFail($doctor->id));

    return $visit;
}

function writeDocument(string $visit, string $kind): void
{
    test()->postJson("/appointments/{$visit}/written-documents", ['kind' => $kind, 'title' => 'Note', 'body' => '<p>Seen today.</p>'])->assertCreated();
}

/** @return list<array{code: string, total: string}> */
function draftLines(Patient $patient): array
{
    Sanctum::actingAs(User::factory()->withRole(RoleName::Receptionist->value)->create());
    $draft = test()->getJson("/billing/invoices?filter[patient_id][eq]={$patient->id}&filter[status][eq]=draft")->json('data.0');
    if ($draft === null) {
        return [];
    }

    return array_map(fn (array $i): array => ['code' => $i['billable_service']['code'] ?? '', 'total' => $i['line_total']['amount']],
        test()->getJson("/billing/invoices/{$draft['id']}")->json('data.items'));
}

it('charges a medical report onto the patient draft and lists the paid documents for the doctor', function () {
    $patient = FrontDesk::patient();
    $visit = feeDocumentVisit($patient);

    $this->getJson('/billing/document-fees')->assertOk()
        ->assertJsonFragment(['kind' => 'medical_report', 'name' => 'Medical report'])
        ->assertJsonFragment(['kind' => 'fitness_certificate']);
    writeDocument($visit, 'medical_report');

    expect(draftLines($patient))->toBe([['code' => 'FEE-MEDICAL-REPORT', 'total' => '10.000']]);
});

it('keeps attendance certificates, letters and switched-off kinds free', function () {
    BillableService::query()->where('code', 'FEE-FITNESS-CERT')->update(['is_archived' => true]);
    $patient = FrontDesk::patient();
    $visit = feeDocumentVisit($patient);

    $this->getJson('/billing/document-fees')->assertJsonMissing(['kind' => 'fitness_certificate']);
    writeDocument($visit, 'attendance_certificate');
    writeDocument($visit, 'general_letter');
    writeDocument($visit, 'fitness_certificate');

    expect(draftLines($patient))->toBe([]);
});
