<?php

use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Modules\Accounting\Fiscal\Contracts\FiscalPeriodServiceInterface;
use App\Modules\Accounting\Fiscal\Data\FiscalYearData;
use App\Modules\Appointments\Doctors\Models\Doctor;
use App\Modules\Appointments\Orders\Models\ClinicalOrder;
use App\Modules\Appointments\Patients\Models\Patient;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Enums\GenericStatus;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FrontDesk;

beforeEach(function () {
    $this->seed([CountrySeeder::class, RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);
    Notification::fake();
    Storage::fake('local');
    // Sunday 1 Nov 2026, 08:00 in Bahrain.
    $this->travelTo(CarbonImmutable::parse('2026-11-01 05:00:00', 'UTC'));
    app(FiscalPeriodServiceInterface::class)->createYear(FiscalYearData::calendar(2026));
});

/** Patient with a portal account (role `patient`, rights from config), signed in. */
function portalPatient(): Patient
{
    $patient = FrontDesk::patient();
    Sanctum::actingAs(User::factory()->state(['id' => $patient->id, 'user_type' => UserType::Patient])->withRole(RoleName::Patient->value)->create());

    return $patient;
}

function portalSignIn(string $userId): void
{
    Sanctum::actingAs(User::query()->findOrFail($userId));
}

function portalPost(string $url, array $payload): TestResponse
{
    return test()->postJson($url, $payload, ['Idempotency-Key' => (string) Str::uuid()]);
}

/** Receptionist books, checks in; the doctor starts and completes a consultation (the visit lands on a draft invoice). */
function portalCompletedVisit(Doctor $doctor, Patient $patient): void
{
    Sanctum::actingAs(User::factory()->withRole(RoleName::Receptionist->value)->create());
    $id = portalPost('/api/v1/appointments', [
        'patient_id' => $patient->id, 'provider_id' => $doctor->id, 'type' => 'consultation',
        'scheduled_date' => '2026-11-01', 'start_time' => '08:15', 'source' => 'walk_in',
    ])->assertCreated()->json('data.id');
    test()->patchJson("/api/v1/appointments/{$id}/check-in")->assertOk();
    portalSignIn($doctor->id);
    test()->patchJson("/api/v1/appointments/{$id}/start")->assertOk();
    test()->patchJson("/api/v1/appointments/{$id}/complete", [])->assertOk();
}

it('books for the signed-in patient only, at least two hours ahead, and cancels only their own bookings', function () {
    $doctor = FrontDesk::doctor();
    $other = FrontDesk::patient();
    Sanctum::actingAs(User::factory()->withRole(RoleName::Receptionist->value)->create());
    $othersBooking = portalPost('/api/v1/appointments', [
        'patient_id' => $other->id, 'provider_id' => $doctor->id, 'type' => 'consultation',
        'scheduled_date' => '2026-11-02', 'start_time' => '09:00', 'source' => 'phone',
    ])->assertCreated()->json('data.id');
    $this->getJson('/api/v1/portal/profile')->assertForbidden();

    $patient = portalPatient();
    $this->getJson('/api/v1/doctors')->assertOk()->assertJsonPath('data.0.id', $doctor->id)->assertJsonMissingPath('data.0.phone');
    // 08:00 now: online slots start two hours later.
    $this->getJson("/api/v1/portal/slots?doctor_id={$doctor->id}&date=2026-11-01&type=consultation")
        ->assertOk()->assertJsonPath('data.0.start_time', '10:00');
    portalPost('/api/v1/portal/appointments', ['doctor_id' => $doctor->id, 'type' => 'consultation', 'scheduled_date' => '2026-11-01', 'start_time' => '09:00'])
        ->assertStatus(422)->assertJsonPath('error_code', 'BOOKING_WINDOW_EXCEEDED');
    portalPost('/api/v1/portal/appointments', ['doctor_id' => $doctor->id, 'type' => 'procedure', 'scheduled_date' => '2026-11-02', 'start_time' => '10:00'])
        ->assertStatus(422)->assertJsonValidationErrors('type');

    $own = portalPost('/api/v1/portal/appointments', [
        'doctor_id' => $doctor->id, 'type' => 'consultation', 'scheduled_date' => '2026-11-02', 'start_time' => '10:00',
        'reason' => 'Knee pain', 'patient_id' => $other->id,
    ])->assertCreated()
        ->assertJsonPath('data.patient_id', $patient->id)->assertJsonPath('data.status', 'pending')->assertJsonPath('data.source', 'portal')
        ->json('data.id');

    // The staff endpoints take any patient id or search everyone: a patient's portal rights do not open them.
    portalPost('/api/v1/appointments', [
        'patient_id' => $other->id, 'provider_id' => $doctor->id, 'type' => 'consultation',
        'scheduled_date' => '2026-11-03', 'start_time' => '09:00', 'source' => 'walk_in',
    ])->assertForbidden();
    $this->postJson('/api/v1/people/duplicates', ['first_name' => 'Any', 'last_name' => 'One', 'date_of_birth' => '1990-01-01'])->assertForbidden();

    $this->getJson('/api/v1/appointments')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own);
    $this->get("/api/v1/codes/appointment/{$own}")->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    $this->get("/api/v1/codes/appointment/{$othersBooking}")->assertNotFound();

    $this->patchJson("/api/v1/portal/appointments/{$othersBooking}/cancel", ['reason' => 'Not mine'])->assertNotFound();
    $this->patchJson("/api/v1/portal/appointments/{$own}/cancel", ['reason' => 'Feeling better'])->assertOk()
        ->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.cancelled_by', 'patient');
});

it('lets patients update how to reach them and shows released results (with their files) and issued invoices only', function () {
    $doctor = FrontDesk::doctor();
    $patient = FrontDesk::patient();
    $patient->forceFill(['notes' => 'Prefers afternoon visits'])->save();
    portalCompletedVisit($doctor, $patient);
    $result = ClinicalOrder::query()->create([
        'patient_id' => $patient->id, 'ordered_by' => $doctor->id, 'type' => 'lab_test', 'status' => GenericStatus::Completed, 'priority' => 'medium',
        'completed_at' => now(), 'report' => 'All within the normal range.', 'clinical_notes' => 'Routine check.',
    ]);
    $pending = ClinicalOrder::query()->create([
        'patient_id' => $patient->id, 'ordered_by' => $doctor->id, 'type' => 'xray', 'status' => GenericStatus::Pending, 'priority' => 'medium', 'report' => 'Draft text',
    ]);

    Sanctum::actingAs(User::factory()->withRole(RoleName::Admin->value)->create());
    $upload = fn (ClinicalOrder $order): string => $this->post('/api/v1/attachments', [
        'files' => [UploadedFile::fake()->createWithContent('result.pdf', "%PDF-1.4\nresult\n%%EOF")],
        'attachable_type' => 'clinical_order', 'attachable_id' => $order->id, 'category' => 'lab_result',
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.saved.0.id');
    [$releasedFile, $pendingFile] = [$upload($result), $upload($pending)];

    Sanctum::actingAs(User::factory()->state(['id' => $patient->id, 'user_type' => UserType::Patient])->withRole(RoleName::Patient->value)->create());
    $profile = $this->getJson('/api/v1/portal/profile')->assertOk()->assertJsonMissingPath('data.notes')->json('data');
    $this->putJson('/api/v1/portal/profile', [
        'version' => $profile['version'], 'person_version' => $profile['person']['version'],
        'phone' => '+97333112233', 'emergency_contact_name' => 'Sara Yousif', 'emergency_contact_phone' => '+97336001122',
        'first_name' => 'Changed',
    ])->assertOk()
        ->assertJsonPath('data.phone', '+97333112233')->assertJsonPath('data.emergency_contact_name', 'Sara Yousif')
        ->assertJsonPath('data.person.first_name', $profile['person']['first_name']);

    $this->getJson("/api/v1/clinical-orders/{$result->id}")->assertOk()
        ->assertJsonPath('data.report', 'All within the normal range.')->assertJsonMissingPath('data.clinical_notes');
    $this->getJson("/api/v1/clinical-orders/{$pending->id}")->assertOk()->assertJsonMissingPath('data.report');
    // Result files are medical: released to the patient with the completed result only.
    $this->getJson("/api/v1/attachments/{$releasedFile}/download")->assertOk();
    $this->getJson("/api/v1/attachments/{$pendingFile}/download")->assertForbidden();

    // The visit's draft stays at the desk until it is issued.
    $this->getJson('/api/v1/billing/invoices')->assertOk()->assertJsonCount(0, 'data');
    Sanctum::actingAs(User::factory()->withRole(RoleName::Receptionist->value)->create());
    $draft = $this->getJson("/api/v1/billing/invoices?filter[patient_id][eq]={$patient->id}")->json('data.0.id');
    portalPost("/api/v1/billing/invoices/{$draft}/issue", [])->assertOk();
    portalSignIn($patient->id);
    $this->getJson('/api/v1/billing/invoices')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'issued');
});
