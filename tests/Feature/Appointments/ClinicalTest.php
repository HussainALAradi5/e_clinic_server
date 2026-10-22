<?php

use App\Enums\Appointments\ConsentType;
use App\Enums\Appointments\RoomType;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Enums\Payments\ServiceCategory;
use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Doctors\Models\Doctor;
use App\Modules\Appointments\Treatments\Events\TreatmentAttendanceAlert;
use App\Modules\Identity\Attachments\Models\Attachment;
use App\Modules\Identity\Users\Models\User;
use App\Modules\Reference\Companies\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\CountrySeeder;
use Database\Seeders\Icd10Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FrontDesk;

beforeEach(function () {
    $this->seed([CountrySeeder::class, RolesAndPermissionsSeeder::class, Icd10Seeder::class]);
    Notification::fake();
    // Sunday 1 Nov 2026, 08:00 in Bahrain.
    $this->travelTo(CarbonImmutable::parse('2026-11-01 05:00:00', 'UTC'));
});

function signedIn(string $id): User
{
    $user = User::query()->findOrFail($id);
    Sanctum::actingAs($user);

    return $user;
}

function deskUser(RoleName $role = RoleName::Receptionist): User
{
    $user = User::factory()->withRole($role->value)->create();
    Sanctum::actingAs($user);

    return $user;
}

function postKeyed(string $url, array $payload): TestResponse
{
    return test()->postJson($url, $payload, ['Idempotency-Key' => (string) Str::uuid()]);
}

/** @return array<string, mixed> */
function plan(Doctor $doctor, string $patientId, array $overrides = []): array
{
    return [
        'patient_id' => $patientId, 'doctor_id' => $doctor->id, 'type' => 'physiotherapy', 'title' => 'Knee physiotherapy',
        'diagnosis' => 'Patellofemoral pain', 'plan' => 'Strengthening, 2 per week', 'priority' => 'medium', 'planned_sessions' => 3, ...$overrides,
    ];
}

/** Session times of the therapist (08:00–16:00) on the given day. */
function sessionAt(string $providerId, string $date, string $time, ?string $roomId = null): array
{
    return ['provider_id' => $providerId, 'scheduled_date' => $date, 'start_time' => $time, 'room_id' => $roomId];
}

it('prescribes a treatment, holds urgent plans to 24 hours and books sessions with the plan length', function () {
    $doctor = FrontDesk::doctor();
    $therapist = FrontDesk::therapist();
    $room = FrontDesk::room('PT-1', RoomType::SessionRoom);
    $patient = FrontDesk::patient();
    signedIn($doctor->id);

    postKeyed('/api/v1/treatments', plan($doctor, $patient->id, ['priority' => 'urgent', 'sessions' => [sessionAt($therapist->id, '2026-11-03', '09:00')]]))
        ->assertUnprocessable()->assertJsonPath('error_code', 'PRIORITY_DEADLINE_MISSED');

    $treatment = postKeyed('/api/v1/treatments', plan($doctor, $patient->id, ['priority' => 'urgent', 'sessions' => [sessionAt($therapist->id, '2026-11-01', '14:00', $room->id)]]))
        ->assertCreated()
        ->assertJsonPath('data.code', fn (string $c) => str_starts_with($c, 'TRT-'))
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.session_duration_minutes', 45)
        ->assertJsonPath('data.diagnosis', 'Patellofemoral pain')
        ->assertJsonPath('data.progress.upcoming_sessions', 1)
        ->assertJsonPath('data.progress.remaining_sessions', 3)
        ->json('data');

    $first = Appointment::query()->where('treatment_id', $treatment['id'])->sole();
    expect($first->session_number)->toBe(1)->and(substr($first->end_time, 0, 5))->toBe('14:45')->and($first->type->value)->toBe('treatment_session');

    // Reception books the rest; the fourth is refused (plan of 3).
    deskUser();
    $this->getJson('/api/v1/appointments/providers?type=treatment_session')->assertOk()->assertJsonCount(2, 'data')->assertJsonFragment(['id' => $therapist->id, 'is_doctor' => false]);
    $this->getJson('/api/v1/appointments/providers?type=consultation')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/treatments/'.$treatment['id'])->assertOk()->assertJsonMissingPath('data.diagnosis');
    postKeyed("/api/v1/treatments/{$treatment['id']}/sessions", sessionAt($therapist->id, '2026-11-02', '10:00'))->assertCreated()->assertJsonPath('data.session_number', 2);
    postKeyed("/api/v1/treatments/{$treatment['id']}/sessions", sessionAt($therapist->id, '2026-11-04', '10:00'))->assertCreated()->assertJsonPath('data.session_number', 3);
    postKeyed("/api/v1/treatments/{$treatment['id']}/sessions", sessionAt($therapist->id, '2026-11-05', '10:00'))
        ->assertUnprocessable()->assertJsonPath('error_code', 'SESSION_LIMIT_REACHED');

    // A public booking cannot sneak in a treatment session.
    postKeyed('/api/v1/appointments', ['patient_id' => $patient->id, 'provider_id' => $therapist->id, 'type' => 'treatment_session', 'scheduled_date' => '2026-11-02', 'start_time' => '11:00', 'source' => 'phone'])
        ->assertUnprocessable();
});

it('tracks attendance from the sessions, alerts after two misses and completes the plan on the last session', function () {
    Event::fake([TreatmentAttendanceAlert::class]);
    $doctor = FrontDesk::doctor();
    $therapist = FrontDesk::therapist();
    $patient = FrontDesk::patient();
    signedIn($doctor->id);
    $id = postKeyed('/api/v1/treatments', plan($doctor, $patient->id, ['planned_sessions' => 2, 'sessions' => [
        sessionAt($therapist->id, '2026-11-01', '09:00'), sessionAt($therapist->id, '2026-11-01', '11:00'),
    ]]))->assertCreated()->json('data.id');
    [$one, $two] = Appointment::query()->where('treatment_id', $id)->orderBy('session_number')->get()->all();

    // 09:00 missed, 11:00 missed → two absences in a row.
    $this->travelTo(CarbonImmutable::parse('2026-11-01 08:30:00', 'UTC'));
    deskUser();
    $this->patchJson("/api/v1/appointments/{$one->id}/no-show")->assertOk();
    $this->patchJson("/api/v1/appointments/{$two->id}/no-show")->assertOk();
    Event::assertDispatched(TreatmentAttendanceAlert::class, fn (TreatmentAttendanceAlert $e) => $e->consecutiveAbsences === 2);

    $this->getJson("/api/v1/treatments/{$id}")->assertOk()
        ->assertJsonPath('data.progress.absent_sessions', 2)
        ->assertJsonPath('data.progress.attendance_rate', 0)
        ->assertJsonPath('data.progress.consecutive_absences', 2)
        ->assertJsonPath('data.progress.remaining_sessions', 2);
    $this->getJson('/api/v1/treatments/queue')->assertOk()->assertJsonPath('data.0.id', $id);

    // Missed numbers are reused; attending both completes the plan.
    $three = postKeyed("/api/v1/treatments/{$id}/sessions", sessionAt($therapist->id, '2026-11-01', '13:00'))->assertCreated()->assertJsonPath('data.session_number', 1)->json('data.id');
    $four = postKeyed("/api/v1/treatments/{$id}/sessions", sessionAt($therapist->id, '2026-11-01', '14:00'))->assertCreated()->assertJsonPath('data.session_number', 2)->json('data.id');
    $this->getJson('/api/v1/treatments/queue')->assertOk()->assertJsonCount(0, 'data');

    foreach ([$three, $four] as $session) {
        $this->patchJson("/api/v1/appointments/{$session}/check-in")->assertOk();
    }
    signedIn($therapist->id);
    foreach ([$three, $four] as $session) {
        $this->patchJson("/api/v1/appointments/{$session}/start")->assertOk();
        $this->patchJson("/api/v1/appointments/{$session}/complete")->assertOk();
        if ($session === $three) {
            $this->getJson("/api/v1/treatments/{$id}")->assertJsonPath('data.status', 'in_progress')->assertJsonPath('data.start_date', '2026-11-01');
        }
    }
    $this->getJson("/api/v1/treatments/{$id}")->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.progress.attended_sessions', 2)
        ->assertJsonPath('data.progress.attendance_rate', 50);
    $this->getJson("/api/v1/treatments/{$id}/sessions")->assertOk()->assertJsonCount(4, 'data')->assertJsonPath('data.2.duration_minutes', 45);
    deskUser();
    postKeyed("/api/v1/treatments/{$id}/sessions", sessionAt($therapist->id, '2026-11-02', '09:00'))->assertStatus(409)->assertJsonPath('error_code', 'TREATMENT_NOT_OPEN');
});

it('cancels a plan with its booked sessions and keeps the priority change reason in the audit trail', function () {
    $doctor = FrontDesk::doctor();
    $therapist = FrontDesk::therapist();
    $patient = FrontDesk::patient();
    signedIn($doctor->id);
    $treatment = postKeyed('/api/v1/treatments', plan($doctor, $patient->id, ['sessions' => [sessionAt($therapist->id, '2026-11-03', '09:00')]]))->json('data');

    $this->patchJson("/api/v1/treatments/{$treatment['id']}/priority", ['priority' => 'high', 'reason' => 'Pain worse'])->assertOk()->assertJsonPath('data.priority', 'high');
    $this->assertDatabaseHas('audit_logs', ['auditable_id' => $treatment['id'], 'action' => 'updated']);
    $this->putJson("/api/v1/treatments/{$treatment['id']}", ['version' => 1, 'planned_sessions' => 6])->assertStatus(409)->assertJsonPath('error_code', 'CONCURRENT_MODIFICATION');
    $this->putJson("/api/v1/treatments/{$treatment['id']}", ['version' => 2, 'planned_sessions' => 6])->assertOk()->assertJsonPath('data.planned_sessions', 6);

    $this->patchJson("/api/v1/treatments/{$treatment['id']}/cancel", ['reason' => 'Patient moved abroad'])->assertOk()->assertJsonPath('data.status', 'cancelled');
    expect(Appointment::query()->where('treatment_id', $treatment['id'])->sole()->status->value)->toBe('cancelled');
    $this->putJson("/api/v1/treatments/{$treatment['id']}", ['version' => 4, 'title' => 'x'])->assertStatus(409)->assertJsonPath('error_code', 'TREATMENT_NOT_OPEN');
});

it('runs an X-ray order from the work list to an abnormal result with images', function () {
    $doctor = FrontDesk::doctor();
    $patient = FrontDesk::patient();
    $xray = FrontDesk::service('XR-CHEST', '20.000', ServiceCategory::Radiology);
    $room = FrontDesk::room('XR-1', RoomType::Xray);
    $consult = FrontDesk::service();
    signedIn($doctor->id);

    $this->postJson('/api/v1/clinical-orders', ['type' => 'xray', 'patient_id' => $patient->id, 'billable_service_id' => $consult->id, 'priority' => 'high'], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertUnprocessable()->assertJsonStructure(['errors' => ['billable_service_id']]);
    $order = postKeyed('/api/v1/clinical-orders', ['type' => 'xray', 'patient_id' => $patient->id, 'billable_service_id' => $xray->id, 'body_part' => 'Chest PA', 'clinical_notes' => 'Cough 3 weeks', 'priority' => 'high'])
        ->assertCreated()
        ->assertJsonPath('data.code', fn (string $c) => str_starts_with($c, 'ORD-'))
        ->assertJsonPath('data.ordered_by', $doctor->id)
        ->assertJsonPath('data.description', 'XR-CHEST — Chest PA')
        ->json('data');

    $tech = signedIn(FrontDesk::employee(UserType::MedicalStaff, RoleName::Nurse)->id);
    $this->getJson('/api/v1/clinical-orders/worklist?types[]=xray')->assertOk()->assertJsonPath('data.0.id', $order['id']);
    $this->patchJson("/api/v1/clinical-orders/{$order['id']}/start", ['room_id' => FrontDesk::room('R-9')->id])->assertStatus(409)->assertJsonPath('error_code', 'ROOM_UNAVAILABLE');
    $this->patchJson("/api/v1/clinical-orders/{$order['id']}/start", ['room_id' => $room->id])->assertOk()->assertJsonPath('data.status', 'in_progress')->assertJsonPath('data.performer.id', $tech->id);
    $this->patchJson("/api/v1/clinical-orders/{$order['id']}/complete", ['report' => 'Right lower lobe opacity.', 'is_abnormal' => true])
        ->assertUnprocessable()->assertJsonPath('error_code', 'ORDER_RESULT_MISSING');

    Attachment::query()->create([
        'attachable_type' => 'clinical_order', 'attachable_id' => $order['id'], 'category' => 'xray_image', 'original_name' => 'chest.png',
        'mime_type' => 'image/png', 'size_bytes' => 10, 'disk' => 'local', 'path' => 'x', 'sha256' => str_repeat('a', 64),
    ]);
    $this->patchJson("/api/v1/clinical-orders/{$order['id']}/complete", ['report' => 'Right lower lobe opacity.', 'is_abnormal' => true, 'reported_by' => $doctor->id])
        ->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.is_abnormal', true)->assertJsonCount(1, 'data.attachments');
    $this->assertDatabaseHas('notifications', ['template' => 'order.abnormal', 'person_id' => $doctor->id]);

    deskUser();
    $this->getJson("/api/v1/clinical-orders/{$order['id']}")->assertOk()->assertJsonMissingPath('data.report')->assertJsonMissingPath('data.attachments');
    $this->getJson("/api/v1/patients/{$patient->id}/timeline")->assertOk()->assertJsonFragment(['kind' => 'order']);
});

it('closes an internal referral when the referred visit is completed and checks outside referrals', function () {
    $gp = FrontDesk::doctor();
    $cardio = FrontDesk::doctor();
    $patient = FrontDesk::patient();
    signedIn($gp->id);

    postKeyed('/api/v1/clinical-orders', ['type' => 'referral', 'patient_id' => $patient->id, 'priority' => 'medium'])
        ->assertUnprocessable()->assertJsonPath('error_code', 'REFERRAL_TARGET_MISSING');
    $insurer = Company::query()->create(['type' => 'insurer', 'name' => 'Gulf Insurance', 'country_code' => 'BH']);
    postKeyed('/api/v1/clinical-orders', ['type' => 'referral', 'patient_id' => $patient->id, 'priority' => 'medium', 'referred_to_company_id' => $insurer->id])
        ->assertUnprocessable()->assertJsonStructure(['errors' => ['referred_to_company_id']]);
    $referral = postKeyed('/api/v1/clinical-orders', ['type' => 'referral', 'patient_id' => $patient->id, 'priority' => 'high', 'referred_to_doctor_id' => $cardio->id, 'clinical_notes' => 'Palpitations'])
        ->assertCreated()->json('data.id');

    deskUser();
    $visit = postKeyed('/api/v1/appointments', ['patient_id' => $patient->id, 'provider_id' => $cardio->id, 'type' => 'consultation', 'scheduled_date' => '2026-11-01', 'start_time' => '10:00', 'source' => 'referral'])->json('data.id');
    $other = postKeyed('/api/v1/appointments', ['patient_id' => $patient->id, 'provider_id' => $gp->id, 'type' => 'consultation', 'scheduled_date' => '2026-11-01', 'start_time' => '11:00', 'source' => 'phone'])->json('data.id');
    $this->patchJson("/api/v1/clinical-orders/{$referral}/referral-appointment", ['appointment_id' => $other])->assertUnprocessable();
    $this->patchJson("/api/v1/clinical-orders/{$referral}/referral-appointment", ['appointment_id' => $visit])->assertOk()->assertJsonPath('data.status', 'in_progress');

    $this->patchJson("/api/v1/appointments/{$visit}/check-in")->assertOk();
    signedIn($cardio->id);
    $this->patchJson("/api/v1/appointments/{$visit}/start")->assertOk();
    $this->patchJson("/api/v1/appointments/{$visit}/complete")->assertOk();
    $this->getJson("/api/v1/clinical-orders/{$referral}")->assertOk()->assertJsonPath('data.status', 'completed');
});

it('documents a visit: vitals at check-in, SOAP notes and ICD-10, signed by the provider, then only addenda', function () {
    $doctor = FrontDesk::doctor();
    $patient = FrontDesk::patient();
    deskUser();
    $visit = postKeyed('/api/v1/appointments', ['patient_id' => $patient->id, 'provider_id' => $doctor->id, 'type' => 'consultation', 'scheduled_date' => '2026-11-01', 'start_time' => '09:00', 'source' => 'phone'])->json('data.id');
    $this->getJson("/api/v1/appointments/{$visit}/medical-record")->assertForbidden();
    $this->patchJson("/api/v1/appointments/{$visit}/check-in")->assertOk();

    deskUser(RoleName::Nurse);
    $this->getJson("/api/v1/appointments/{$visit}/medical-record")->assertOk()->assertJsonPath('data.exists', false);
    $this->putJson("/api/v1/appointments/{$visit}/medical-record", ['vitals' => ['temperature_c' => 38.2, 'pulse_bpm' => 96, 'weight_kg' => 80, 'height_cm' => 180, 'spo2_percent' => 97]])
        ->assertOk()->assertJsonPath('data.vitals.bmi', 24.7)->assertJsonPath('data.version', 1);

    signedIn($doctor->id);
    $j069 = $this->getJson('/api/v1/icd10?search=upper respiratory')->assertOk()->assertJsonPath('data.0.code', 'J06.9')->json('data.0.id');
    $r509 = $this->getJson('/api/v1/icd10?search=R50')->assertOk()->assertJsonPath('data.0.code', 'R50.9')->json('data.0.id');
    $this->getJson('/api/v1/icd10?search=حمى')->assertOk()->assertJsonPath('data.0.code', 'R50.9');

    $this->patchJson("/api/v1/appointments/{$visit}/start")->assertOk();
    $this->putJson("/api/v1/appointments/{$visit}/medical-record", [
        'version' => 1, 'chief_complaint' => 'Fever and sore throat', 'assessment' => 'Viral URTI', 'plan' => 'Fluids, paracetamol',
        'diagnoses' => [['icd10_code_id' => $j069, 'is_primary' => true], ['icd10_code_id' => $r509, 'is_primary' => true]],
    ])->assertUnprocessable()->assertJsonStructure(['errors' => ['diagnoses']]);
    $this->putJson("/api/v1/appointments/{$visit}/medical-record", [
        'version' => 1, 'chief_complaint' => 'Fever and sore throat', 'assessment' => 'Viral URTI', 'plan' => 'Fluids, paracetamol',
        'diagnoses' => [['icd10_code_id' => $j069], ['icd10_code_id' => $r509]],
    ])->assertOk()->assertJsonPath('data.diagnoses.0.code', 'J06.9')->assertJsonPath('data.diagnoses.0.is_primary', true)->assertJsonPath('data.vitals.pulse_bpm', 96);

    deskUser(RoleName::Nurse);
    $this->postJson("/api/v1/appointments/{$visit}/medical-record/sign")->assertForbidden();

    signedIn($doctor->id);
    $this->postJson("/api/v1/appointments/{$visit}/medical-record/addenda", ['text' => 'x'])->assertStatus(409)->assertJsonPath('error_code', 'MEDICAL_RECORD_NOT_SIGNED');
    $this->postJson("/api/v1/appointments/{$visit}/medical-record/sign")->assertOk()->assertJsonPath('data.is_signed', true)->assertJsonPath('data.signed_by.id', $doctor->id);
    $this->putJson("/api/v1/appointments/{$visit}/medical-record", ['version' => 3, 'plan' => 'Antibiotics'])->assertStatus(409)->assertJsonPath('error_code', 'MEDICAL_RECORD_SIGNED');
    $this->postJson("/api/v1/appointments/{$visit}/medical-record/addenda", ['text' => 'Throat swab negative.'])->assertCreated()->assertJsonPath('data.addenda.0.text', 'Throat swab negative.');
    expect(DB::table('medical_records')->value('assessment'))->not->toContain('Viral');
});

it('requires a valid consent before a procedure that needs one, and revoking it blocks again', function () {
    $doctor = FrontDesk::doctor();
    $procedure = FrontDesk::service('PROC-1', '40.000', ServiceCategory::Procedure, ['consent_type' => ConsentType::Procedure]);
    $doctor->forceFill(['consultation_service_id' => $procedure->id])->save();
    $patient = FrontDesk::patient();
    deskUser();
    $visit = postKeyed('/api/v1/appointments', ['patient_id' => $patient->id, 'provider_id' => $doctor->id, 'type' => 'consultation', 'scheduled_date' => '2026-11-01', 'start_time' => '09:00', 'source' => 'phone'])->json('data.id');
    $this->patchJson("/api/v1/appointments/{$visit}/check-in")->assertOk();

    signedIn($doctor->id);
    $this->patchJson("/api/v1/appointments/{$visit}/start")->assertUnprocessable()->assertJsonPath('error_code', 'CONSENT_REQUIRED')->assertJsonPath('meta.consent_type', 'procedure');

    $consent = [
        'type' => 'procedure', 'appointment_id' => $visit, 'description' => 'Excision of skin lesion; risks: bleeding, infection, scar.',
        'signed_by_name' => $patient->person->full_name, 'signer_relationship' => 'self', 'explained_by' => $doctor->id, 'signed_at' => '2026-11-01T07:55:00+03:00',
    ];
    $this->postJson("/api/v1/patients/{$patient->id}/consents", [...$consent, 'type' => 'general_treatment'])->assertUnprocessable()->assertJsonStructure(['errors' => ['type']]);
    $id = $this->postJson("/api/v1/patients/{$patient->id}/consents", $consent)->assertCreated()
        ->assertJsonPath('data.code', fn (string $c) => str_starts_with($c, 'CNS-'))->assertJsonPath('data.is_valid', true)->assertJsonPath('data.scope.kind', 'appointment')->json('data.id');
    $this->patchJson("/api/v1/appointments/{$visit}/start")->assertOk();

    $this->patchJson("/api/v1/consents/{$id}/revoke", ['reason' => 'Patient changed their mind'])->assertOk()->assertJsonPath('data.is_valid', false);
    $this->getJson("/api/v1/patients/{$patient->id}/consents")->assertOk()->assertJsonCount(1, 'data');

    $child = FrontDesk::patient();
    $child->person->forceFill(['date_of_birth' => '2016-05-05'])->save();
    deskUser();
    $this->postJson("/api/v1/patients/{$child->id}/consents", [...$consent, 'type' => 'general_treatment', 'appointment_id' => null, 'signed_by_name' => 'Child'])
        ->assertUnprocessable()->assertJsonStructure(['errors' => ['signer_relationship']]);
});
