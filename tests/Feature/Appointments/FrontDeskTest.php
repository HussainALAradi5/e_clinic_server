<?php

use App\Enums\Appointments\RoomType;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Events\Appointments\AppointmentBooked;
use App\Interfaces\Appointments\AppointmentServiceInterface;
use App\Listeners\Appointments\SendAppointmentNotifications;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\Doctor;
use App\Models\Identity\AuditLog;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FrontDesk;

beforeEach(function () {
    $this->seed([CountrySeeder::class, RolesAndPermissionsSeeder::class]);
    Notification::fake();
    // Sunday 1 Nov 2026, 08:00 in Bahrain.
    $this->travelTo(CarbonImmutable::parse('2026-11-01 05:00:00', 'UTC'));
});

function receptionist(): User
{
    $user = User::factory()->withRole(RoleName::Receptionist->value)->create();
    Sanctum::actingAs($user);

    return $user;
}

/** @return array<string, mixed> */
function booking(Doctor $doctor, string $patientId, array $overrides = []): array
{
    return [
        'patient_id' => $patientId, 'provider_id' => $doctor->id, 'type' => 'consultation',
        'scheduled_date' => '2026-11-02', 'start_time' => '09:00', 'source' => 'phone', ...$overrides,
    ];
}

function book(array $payload): TestResponse
{
    return test()->postJson('/appointments', $payload, ['Idempotency-Key' => (string) Str::uuid()]);
}

it('registers a patient once: a new person, then the same person again is reported, not duplicated', function () {
    receptionist();
    $payload = ['person' => ['first_name' => 'Maryam', 'last_name' => 'Ali', 'gender' => 'female', 'date_of_birth' => '1995-05-05', 'phone' => '+97336550000', 'national_id' => '950505123']];

    $id = $this->postJson('/patients', $payload)->assertCreated()
        ->assertJsonPath('data.full_name', 'Maryam Ali')
        ->assertJsonMissingPath('data.allergies')
        ->json('data.id');

    $this->postJson('/patients', $payload)->assertStatus(409)
        ->assertJsonPath('error_code', 'PATIENT_ALREADY_REGISTERED')->assertJsonPath('meta.patient_id', $id);
    $this->getJson('/patients?national_id=950505123')->assertOk()->assertJsonPath('data.0.id', $id);
    $this->getJson('/patients?search=maryam')->assertOk()->assertJsonCount(1, 'data');
});

it('offers free slots inside working hours and books with the doctor\'s price and default room', function () {
    receptionist();
    $room = FrontDesk::room();
    $doctor = FrontDesk::doctor(['default_room_id' => $room->id]);
    $patient = FrontDesk::patient();

    $slots = $this->getJson("/appointments/available-slots?provider_id={$doctor->id}&date=2026-11-02&type=consultation")->assertOk()->json('data');
    expect($slots)->toHaveCount(16)->and($slots[0]['start_time'])->toBe('08:00')->and($slots[15]['end_time'])->toBe('12:00');

    book(booking($doctor, $patient->id))->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.end_time', '09:15')
        ->assertJsonPath('data.room.code', 'R-101')
        ->assertJsonPath('data.billable_service.price.amount', '15.000');

    $after = collect($this->getJson("/appointments/available-slots?provider_id={$doctor->id}&date=2026-11-02&type=consultation")->json('data'));
    expect($after)->toHaveCount(15)->and($after->pluck('start_time'))->not->toContain('09:00');
});

it('refuses double bookings, times outside working hours, past times and dates beyond the horizon', function () {
    receptionist();
    $doctor = FrontDesk::doctor();
    $patient = FrontDesk::patient();
    book(booking($doctor, $patient->id))->assertCreated();

    book(booking($doctor, FrontDesk::patient()->id, ['start_time' => '09:05']))->assertStatus(409)->assertJsonPath('error_code', 'SLOT_UNAVAILABLE');
    book(booking(FrontDesk::doctor(), $patient->id))->assertStatus(409)->assertJsonPath('error_code', 'PATIENT_DOUBLE_BOOKED');
    book(booking($doctor, $patient->id, ['start_time' => '13:00']))->assertStatus(409)->assertJsonPath('error_code', 'SLOT_UNAVAILABLE');
    book(booking($doctor, $patient->id, ['scheduled_date' => '2026-11-01', 'start_time' => '07:00']))->assertStatus(409);
    book(booking($doctor, $patient->id, ['scheduled_date' => '2027-03-01']))->assertStatus(422)->assertJsonPath('error_code', 'BOOKING_WINDOW_EXCEEDED');

    $doctor->forceFill(['is_accepting_patients' => false])->save();
    book(booking($doctor, $patient->id, ['start_time' => '10:00']))->assertStatus(409)->assertJsonPath('error_code', 'DOCTOR_NOT_AVAILABLE');
});

it('keeps rooms to one booking at a time and only of a matching type', function () {
    receptionist();
    $room = FrontDesk::room();
    $xray = FrontDesk::room('X-1', RoomType::Xray);
    book(booking(FrontDesk::doctor(), FrontDesk::patient()->id, ['room_id' => $room->id]))->assertCreated();

    book(booking(FrontDesk::doctor(), FrontDesk::patient()->id, ['room_id' => $room->id]))->assertStatus(409)->assertJsonPath('error_code', 'ROOM_UNAVAILABLE');
    book(booking(FrontDesk::doctor(), FrontDesk::patient()->id, ['room_id' => $xray->id]))->assertStatus(409)->assertJsonPath('error_code', 'ROOM_UNAVAILABLE');

    $this->deleteJson("/rooms/{$room->id}")->assertForbidden(); // receptionist cannot delete rooms
    Sanctum::actingAs(User::factory()->withRole(RoleName::Admin->value)->withTwoFactor()->create());
    $this->deleteJson("/rooms/{$room->id}")->assertStatus(409)->assertJsonPath('error_code', 'ROOM_IN_USE');
});

it('walks a visit through check-in, start and completion following the state machine', function () {
    receptionist();
    $doctor = FrontDesk::doctor();
    $id = book(booking($doctor, FrontDesk::patient()->id, ['scheduled_date' => '2026-11-01', 'start_time' => '10:00']))->json('data.id');

    $this->patchJson("/appointments/{$id}/start")->assertForbidden(); // receptionists do not treat
    // Check-in opens an hour before the start (08:00 is two hours early), on the visit's day.
    $this->patchJson("/appointments/{$id}/check-in")->assertStatus(409)->assertJsonPath('error_code', 'INVALID_STATE_TRANSITION')
        ->assertJsonPath('message', 'Check-in opens 1 hour before the appointment, on its day.');
    $this->travelTo(CarbonImmutable::parse('2026-11-01 09:05', 'Asia/Bahrain'));
    $this->patchJson("/appointments/{$id}/check-in")->assertOk()->assertJsonPath('data.status', 'checked_in')->assertJsonPath('data.allowed_transitions', ['in_progress']);
    $this->patchJson("/appointments/{$id}/cancel", ['reason' => 'x', 'cancelled_by' => 'clinic'])->assertStatus(409)->assertJsonPath('error_code', 'INVALID_STATE_TRANSITION');

    Sanctum::actingAs($doctor->employee->user);
    $this->patchJson("/appointments/{$id}/start")->assertOk()->assertJsonPath('data.status', 'in_progress');
    $this->patchJson("/appointments/{$id}/complete", ['follow_up_in_days' => 14])->assertOk()
        ->assertJsonPath('data.status', 'completed')->assertJsonPath('data.follow_up_suggested_date', '2026-11-15');

    $history = $this->getJson("/appointments/{$id}/history")->assertOk()->json('data');
    expect(array_column(array_column($history, 'new_values'), 'status'))->toBe(['confirmed', 'checked_in', 'in_progress', 'completed']);
});

it('reschedules into a new linked booking and cancels with a reason; patients only up to two hours before', function () {
    receptionist();
    $doctor = FrontDesk::doctor();
    $patient = FrontDesk::patient();
    $id = book(booking($doctor, $patient->id))->json('data.id');

    $new = $this->postJson("/appointments/{$id}/reschedule", ['scheduled_date' => '2026-11-02', 'start_time' => '09:15'])
        ->assertCreated()->assertJsonPath('data.rescheduled_from.id', $id)->json('data.id');
    expect(Appointment::query()->find($id)->status->value)->toBe('rescheduled');

    $this->patchJson("/appointments/{$new}/cancel", ['reason' => 'Travelling', 'cancelled_by' => 'patient'])->assertOk()->assertJsonPath('data.status', 'cancelled');

    // The patient's own portal account: too late inside the 2-hour window.
    $soon = book(booking($doctor, $patient->id, ['scheduled_date' => '2026-11-01', 'start_time' => '09:30']))->json('data.id');
    $portal = User::factory()->state(['id' => $patient->id, 'user_type' => UserType::Patient])->withRole(RoleName::Patient->value)->create();
    grantPermissions($portal, 'appointments.write', 'appointments.cancel');
    Sanctum::actingAs($portal);
    $this->patchJson("/appointments/{$soon}/cancel", ['reason' => 'Late', 'cancelled_by' => 'patient'])->assertStatus(409)->assertJsonPath('error_code', 'CANCELLATION_WINDOW_EXPIRED');
});

it('marks overdue confirmed bookings as no-shows', function () {
    receptionist();
    $id = book(booking(FrontDesk::doctor(), FrontDesk::patient()->id, ['scheduled_date' => '2026-11-01', 'start_time' => '08:15']))->json('data.id');

    $this->patchJson("/appointments/{$id}/no-show")->assertStatus(409);
    $this->travel(31)->minutes();
    expect(app(AppointmentServiceInterface::class)->markDueNoShows())->toBe(1)
        ->and(Appointment::query()->find($id)->status->value)->toBe('no_show');
});

it('replaces a weekly schedule only when no booking is left outside, and holidays close the day', function () {
    $admin = User::factory()->withRole(RoleName::Admin->value)->withTwoFactor()->create();
    Sanctum::actingAs($admin);
    $doctor = FrontDesk::doctor();
    book(booking($doctor, FrontDesk::patient()->id, ['start_time' => '11:00']))->assertCreated();

    $morning = fn (string $end) => ['effective_from' => '2026-11-02', 'blocks' => collect(range(0, 6))->map(fn (int $d) => ['day_of_week' => $d, 'start_time' => '08:00', 'end_time' => $end])->all()];
    $this->putJson("/providers/{$doctor->id}/schedules", $morning('10:00'))->assertStatus(409)->assertJsonPath('error_code', 'SCHEDULE_HAS_APPOINTMENTS');
    $this->putJson("/providers/{$doctor->id}/schedules", ['effective_from' => '2026-11-02', 'blocks' => [
        ['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '10:00'], ['day_of_week' => 1, 'start_time' => '09:30', 'end_time' => '12:00'],
    ]])->assertStatus(409)->assertJsonPath('error_code', 'SCHEDULE_OVERLAP');
    $this->putJson("/providers/{$doctor->id}/schedules", $morning('12:30'))->assertOk()->assertJsonCount(7, 'data');

    $this->postJson('/schedules/holidays', ['date' => '2026-11-02', 'type' => 'holiday', 'reason' => 'National day'])->assertStatus(409);
    $this->postJson('/schedules/holidays', ['date' => '2026-11-02', 'type' => 'holiday', 'reason' => 'National day', 'cancel_affected' => true])->assertCreated();
    expect(Appointment::query()->first()->status->value)->toBe('cancelled');
    $this->getJson("/appointments/available-slots?provider_id={$doctor->id}&date=2026-11-02&type=consultation")->assertOk()->assertJsonCount(0, 'data');
});

it('shows people by role: a doctor their own patients and staff, HR staff only, a patient only themself', function () {
    $doctor = FrontDesk::doctor();
    $mine = FrontDesk::patient();
    $other = FrontDesk::patient();
    $hr = FrontDesk::employee(UserType::Hr, RoleName::HrManager);
    receptionist();
    book(booking($doctor, $mine->id))->assertCreated();
    $seen = fn (): array => collect(test()->getJson('/people?per_page=100')->assertOk()->json('data'))->pluck('id')->all();

    Sanctum::actingAs(User::query()->findOrFail($doctor->id));
    expect($seen())->toContain($doctor->id, $hr->id, $mine->id)->not->toContain($other->id);
    $this->putJson("/people/{$other->id}", ['version' => 1, 'first_name' => 'X'])->assertForbidden();

    Sanctum::actingAs(User::query()->findOrFail($hr->id));
    expect($seen())->toContain($doctor->id, $hr->id)->not->toContain($mine->id)->not->toContain($other->id);
    $this->putJson("/people/{$mine->id}", ['version' => 1, 'first_name' => 'X'])->assertNotFound();
    $this->putJson("/people/{$doctor->id}", ['version' => 1, 'first_name' => 'Omar'])->assertOk();

    Sanctum::actingAs(User::factory()->state(['id' => $mine->id, 'user_type' => UserType::Patient])->withRole(RoleName::Patient->value)->create());
    expect($seen())->toBe([$mine->id]);
});

it('queues reminders three days and one day before a visit, only those still ahead', function () {
    receptionist();
    $doctor = FrontDesk::doctor();
    $patient = FrontDesk::patient();
    $patient->person->update(['phone' => '+97336550001']);
    // The listener runs after commit; the test's own transaction never commits, so it is called here.
    $notify = fn (string $id) => app(SendAppointmentNotifications::class)->handle(new AppointmentBooked(Appointment::query()->findOrFail($id)));
    $reminders = fn (string $id) => DB::table('notifications')->where('related_id', $id)->where('template', 'appointment.reminder')
        ->where('channel', 'sms')->orderBy('scheduled_for')->pluck('scheduled_for')
        ->map(fn ($at) => CarbonImmutable::parse($at)->format('Y-m-d H:i'))->all();

    // Thursday 5 Nov at 09:00: both reminders are ahead.
    $later = book(booking($doctor, $patient->id, ['scheduled_date' => '2026-11-05']))->assertCreated()->json('data.id');
    $notify($later);
    expect($reminders($later))->toBe(['2026-11-02 09:00', '2026-11-04 09:00']);

    // Tomorrow at 10:00: three days before is already past, only the day-before one is queued.
    $soon = book(booking($doctor, $patient->id, ['scheduled_date' => '2026-11-02', 'start_time' => '10:00']))->assertCreated()->json('data.id');
    $notify($soon);
    expect($reminders($soon))->toBe(['2026-11-01 10:00']);
});

it('tells coming, today, past and missed visits apart, and filters by them', function () {
    receptionist();
    $doctor = FrontDesk::doctor();
    $patient = FrontDesk::patient();
    $today = book(booking($doctor, $patient->id, ['scheduled_date' => '2026-11-01', 'start_time' => '10:00']))->json('data.id');
    $coming = book(booking($doctor, $patient->id, ['scheduled_date' => '2026-11-03']))->json('data.id');
    $past = book(booking($doctor, $patient->id, ['scheduled_date' => '2026-11-04']))->json('data.id');
    $missed = book(booking($doctor, $patient->id, ['scheduled_date' => '2026-11-05']))->json('data.id');
    // Two of them move into the past (one done, one the patient never came to).
    Appointment::query()->whereKey($past)->update(['scheduled_date' => '2026-10-20', 'status' => 'completed']);
    Appointment::query()->whereKey($missed)->update(['scheduled_date' => '2026-10-21', 'status' => 'no_show']);

    $this->getJson("/appointments/{$coming}")->assertOk()->assertJsonPath('data.timing', 'coming');
    $ids = fn (string $timing) => collect($this->getJson("/appointments?timing={$timing}")->assertOk()->json('data'))->pluck('id')->all();
    expect($ids('today'))->toBe([$today])
        ->and($ids('coming'))->toBe([$coming])
        ->and($ids('past'))->toBe([$past])
        ->and($ids('missed'))->toBe([$missed]);
    $this->getJson('/appointments')->assertOk()->assertJsonCount(4, 'data');
    $this->getJson('/appointments?timing=later')->assertStatus(422);
});

it('lets the front desk open a patient portal account, once, with the patient role only', function () {
    receptionist();
    $id = $this->postJson('/patients', ['person' => ['first_name' => 'Noor', 'last_name' => 'Hasan', 'gender' => 'female', 'date_of_birth' => '1990-02-03', 'phone' => '+97336551111', 'national_id' => '900203111', 'email' => 'noor.hasan@example.com']])
        ->assertCreated()->json('data.id');

    $this->postJson("/patients/{$id}/portal-account")->assertCreated();

    expect(User::query()->findOrFail($id)->getRoleNames()->all())->toBe([RoleName::Patient->value]);
    $this->postJson("/patients/{$id}/portal-account")->assertStatus(409)->assertJsonPath('error_code', 'PATIENT_HAS_ACCOUNT');
});

it('lets the front desk correct name, email, birth date and mobile, and records who changed what', function () {
    $receptionist = receptionist();
    $person = Person::factory()->create(['email' => 'old.address@example.com']);

    $this->putJson("/people/{$person->id}", [
        'version' => $person->version,
        'first_name' => 'Corrected',
        'email' => 'new.address@example.com',
        'date_of_birth' => '1990-02-03',
        'phone' => '+97336552222',
    ])->assertOk()->assertJsonPath('data.first_name', 'Corrected')->assertJsonPath('data.email', 'new.address@example.com');

    $entry = AuditLog::query()->where('auditable_id', $person->id)->where('action', 'updated')->latest('id')->firstOrFail();
    expect($entry->user_id)->toBe($receptionist->id)
        ->and($entry->old_values)->toMatchArray(['email' => 'old.address@example.com'])
        ->and($entry->new_values)->toMatchArray(['email' => 'new.address@example.com', 'first_name' => 'Corrected'])
        ->and($entry->new_values)->toHaveKeys(['date_of_birth', 'phone']);
});
