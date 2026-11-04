<?php

use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Interfaces\Identity\CodeServiceInterface;
use App\Models\Appointments\Appointment;
use App\Models\Identity\GeneratedDocument;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FrontDesk;

beforeEach(function () {
    $this->seed([CountrySeeder::class, RolesAndPermissionsSeeder::class]);
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-01 05:00:00', 'UTC'));
});

/** A sick leave issued by a doctor at an in-progress visit; returns [doctor user, patient, sick leave id]. */
function issuedSickLeave(): array
{
    $doctor = FrontDesk::doctor();
    $patient = FrontDesk::patient();
    Sanctum::actingAs(User::factory()->withRole(RoleName::Receptionist->value)->create());
    $visit = test()->postJson('/api/v1/appointments', ['patient_id' => $patient->id, 'provider_id' => $doctor->id, 'type' => 'consultation', 'scheduled_date' => '2026-11-01', 'start_time' => '11:00', 'source' => 'phone'], ['Idempotency-Key' => (string) Str::uuid()])->json('data.id');
    Appointment::query()->whereKey($visit)->update(['status' => 'in_progress']);
    $user = User::query()->findOrFail($doctor->id);
    Sanctum::actingAs($user);
    $id = test()->postJson("/api/v1/appointments/{$visit}/sick-leaves", ['start_date' => '2026-11-01', 'end_date' => '2026-11-02', 'reason' => 'Influenza'])->assertCreated()->json('data.id');

    return [$user, $patient, $id];
}

function png(int $width = 300, int $height = 120): UploadedFile
{
    return UploadedFile::fake()->image('image.png', $width, $height);
}

it('makes a sick leave PDF once per version and reader, with its own DOC code and unique file name', function () {
    [$doctor, , $id] = issuedSickLeave();

    $first = $this->getJson("/api/v1/documents/sick_leave/{$id}")->assertOk()->json('data');
    expect($first['code'])->toStartWith('DOC-')
        ->and($first['file_name'])->toStartWith('sick-leave_SLV-')->toEndWith($first['code'].'.pdf')
        ->and($first['download_path'])->toBe("documents/sick_leave/{$id}/file?download=1");

    // Apps hand the file to the device's viewer through a short-lived signed link (no bearer token needed).
    Sanctum::actingAs(User::factory()->create());
    auth()->forgetGuards();
    $this->get($first['open_url'])->assertOk()->assertHeader('content-type', 'application/pdf');
    Sanctum::actingAs($doctor);

    // Opened again unchanged: the stored file, no new document.
    expect($this->getJson("/api/v1/documents/sick_leave/{$id}")->json('data.code'))->toBe($first['code']);
    $file = $this->get("/api/v1/documents/sick_leave/{$id}/file?download=1")->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($file->getContent())->toStartWith('%PDF-')
        ->and($file->headers->get('content-disposition'))->toBe('attachment; filename="'.$first['file_name'].'"');
    expect(GeneratedDocument::query()->count())->toBe(1);

    // Changed record: a new version is made.
    $this->travel(1)->minutes();
    $this->patchJson("/api/v1/sick-leaves/{$id}/cancel", ['reason' => 'Issued in error'])->assertOk();
    expect($this->getJson("/api/v1/documents/sick_leave/{$id}")->json('data.code'))->not->toBe($first['code']);
});

it('hides documents from readers without rights and unknown types', function () {
    [, , $id] = issuedSickLeave();

    Sanctum::actingAs(User::factory()->withRole(RoleName::Accountant->value)->create());
    $this->getJson("/api/v1/documents/sick_leave/{$id}")->assertNotFound();
    actingWith('sick_leaves.read_all');
    $this->getJson("/api/v1/documents/not_a_type/{$id}")->assertNotFound();
});

it('lets the patient open their own sick leave', function () {
    [, $patient, $id] = issuedSickLeave();

    Sanctum::actingAs(User::factory()->state(['id' => $patient->id, 'user_type' => UserType::Patient])->withRole(RoleName::Patient->value)->create());
    $this->get("/api/v1/documents/sick_leave/{$id}/file")->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('keeps the clinic profile public to read and admin-only to change', function () {
    $this->getJson('/api/v1/public/clinic-profile')->assertOk()->assertJsonPath('data.name', 'e-Clinic')->assertJsonPath('data.logo_path', null);

    Sanctum::actingAs(User::factory()->withRole(RoleName::Receptionist->value)->create());
    $this->putJson('/api/v1/reference/clinic-profile', ['name' => 'Seef Family Clinic'])->assertForbidden();

    Sanctum::actingAs(User::factory()->withRole(RoleName::Admin->value)->create());
    $this->putJson('/api/v1/reference/clinic-profile', ['name' => 'Seef Family Clinic'])->assertOk()->assertJsonPath('data.name', 'Seef Family Clinic');
    $logo = $this->post('/api/v1/reference/clinic-profile/logo', ['image' => png(200, 200)], ['Accept' => 'application/json'])->assertOk()->json('data.logo_path');
    expect($logo)->toStartWith('public/clinic-profile/logo?v=');
    $this->get('/api/v1/public/clinic-profile/logo')->assertOk()->assertHeader('content-type', 'image/png');
    $this->deleteJson('/api/v1/reference/clinic-profile/logo')->assertOk()->assertJsonPath('data.logo_path', null);
});

it('keeps a signature for staff only', function () {
    [$doctor] = issuedSickLeave();

    $this->getJson('/api/v1/auth/signature')->assertNotFound();
    $this->post('/api/v1/auth/signature', ['signature' => png()], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.has_signature', true);
    $this->get('/api/v1/auth/signature')->assertOk()->assertHeader('content-type', 'image/png');
    $this->deleteJson('/api/v1/auth/signature')->assertNoContent();

    Sanctum::actingAs(User::factory()->state(['user_type' => UserType::Patient])->withRole(RoleName::Patient->value)->create());
    $this->post('/api/v1/auth/signature', ['signature' => png()], ['Accept' => 'application/json'])->assertForbidden()->assertJsonPath('error_code', 'SIGNATURE_STAFF_ONLY');
});

it('reads a scanned check link as well as the bare code, and names the document to open', function () {
    [, , $id] = issuedSickLeave();
    $codes = app(CodeServiceInterface::class);
    $code = $this->getJson("/api/v1/sick-leaves/{$id}")->json('data.code');
    $link = $codes->verifyUrl($codes->payloadFor('sick_leave', $code));
    expect($link)->toContain('/verify?code=sick_leave');

    $this->getJson('/api/v1/public/verify/'.$codes->payloadFor('sick_leave', $code))->assertOk()->assertJsonPath('data.document', 'sick_leave');
    actingWith('codes.read_all', 'codes.scan', 'sick_leaves.read_all');
    $this->postJson('/api/v1/codes/scan', ['payload' => $link, 'purpose' => 'identify'])->assertOk()->assertJsonPath('data.id', $id);
});

it('offers ready-made templates read-only, and lets staff write, copy and delete their own in clean rich text', function () {
    issuedSickLeave();

    $list = $this->getJson('/api/v1/document-templates?per_page=50')->assertOk()->json('data');
    expect(count($list))->toBe(6)->and(collect($list)->every(fn (array $t): bool => $t['is_system'] && ! $t['is_editable']))->toBeTrue();
    $this->getJson('/api/v1/document-templates/placeholders')->assertOk()->assertJsonFragment(['token' => '{{patient_name}}']);

    $system = $list[0]['id'];
    $this->putJson("/api/v1/document-templates/{$system}", ['name' => 'Mine'])->assertStatus(409)->assertJsonPath('error_code', 'TEMPLATE_READ_ONLY');
    $copy = $this->postJson("/api/v1/document-templates/{$system}/duplicate")->assertCreated()->assertJsonPath('data.is_editable', true)->json('data');
    expect($copy['name'])->toEndWith('(copy)')->and($copy['code'])->toStartWith('TPL-');

    $own = $this->postJson('/api/v1/document-templates', [
        'name' => 'Back pain note', 'kind' => 'medical_report',
        'body' => '<p onclick="x()"><strong>{{patient_name}}</strong> <em>rest</em></p><script>alert(1)</script><img src=x>',
    ])->assertCreated()->json('data');
    expect($own['body'])->toBe('<p><strong>{{patient_name}}</strong> <em>rest</em></p>');

    $this->putJson("/api/v1/document-templates/{$own['id']}", ['description' => 'For desk workers'])->assertOk()->assertJsonPath('data.description', 'For desk workers');
    $this->deleteJson("/api/v1/document-templates/{$own['id']}")->assertNoContent();
    $this->getJson("/api/v1/document-templates/{$own['id']}")->assertNotFound();
});

it('lets the visit doctor generate, write and print a document; nobody else writes it', function () {
    [$doctor, $patient, $leave] = issuedSickLeave();
    $visit = $this->getJson("/api/v1/sick-leaves/{$leave}")->json('data.appointment_id');
    $template = collect($this->getJson('/api/v1/document-templates?per_page=50')->json('data'))->firstWhere('kind', 'attendance_certificate');

    $draft = $this->postJson("/api/v1/appointments/{$visit}/written-documents/draft", ['kind' => 'attendance_certificate', 'template_id' => $template['id']])
        ->assertOk()->json('data.body');
    expect($draft)->toContain($patient->person->full_name)->not->toContain('{{');

    $written = $this->postJson("/api/v1/appointments/{$visit}/written-documents", [
        'kind' => 'attendance_certificate', 'title' => 'Attendance certificate', 'body' => $draft.'<p>Seen by {{author_name}}.</p>', 'template_id' => $template['id'],
    ])->assertCreated()->json('data');
    expect($written['code'])->toStartWith('WRD-')->and($written['body'])->toContain('Seen by '.$doctor->person->full_name);
    $this->get("/api/v1/documents/written_document/{$written['id']}/file")->assertOk()->assertHeader('content-type', 'application/pdf');

    // Another doctor does not even see this visit (no existence leak); the front desk cannot write at all.
    Sanctum::actingAs(User::query()->findOrFail(FrontDesk::doctor()->id));
    $this->postJson("/api/v1/appointments/{$visit}/written-documents", ['kind' => 'medical_report', 'title' => 'x', 'body' => '<p>x</p>'])->assertNotFound();
    Sanctum::actingAs(User::factory()->withRole(RoleName::Receptionist->value)->create());
    $this->postJson("/api/v1/appointments/{$visit}/written-documents", ['kind' => 'medical_report', 'title' => 'x', 'body' => '<p>x</p>'])->assertForbidden();
    $this->getJson('/api/v1/written-documents')->assertOk()->assertJsonCount(1, 'data');
});
