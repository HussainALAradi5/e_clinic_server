<?php

use App\Enums\Identity\RoleName;
use App\Enums\Identity\TrackedEntity;
use App\Enums\Identity\UserType;
use App\Models\Appointments\Patient;
use App\Models\Identity\AuditLog;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FrontDesk;

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->seed([CountrySeeder::class, RolesAndPermissionsSeeder::class]);
});

function activityActingAs(RoleName $role, UserType $type = UserType::MedicalStaff): void
{
    Sanctum::actingAs(User::query()->findOrFail(FrontDesk::employee($type, $role)->id));
}

/** A patient whose email was changed once, by a receptionist. */
function activityPatient(): Person
{
    $person = Person::factory()->create(['email' => 'first@example.com']);
    Patient::query()->forceCreate(['id' => $person->id]);
    $person->refresh()->update(['email' => 'second@example.com']);

    return $person;
}

it('shows who changed what on the patient file to reception and admins', function (RoleName $role, UserType $type) {
    activityActingAs($role, $type);
    $person = activityPatient();

    $entries = $this->getJson("/activity/patient_profile/{$person->id}")->assertOk()->json('data');
    $update = collect($entries)->firstWhere('action', 'updated');

    expect($update['changes'])->toContain(['field' => 'email', 'label' => 'Email', 'old' => 'first@example.com', 'new' => 'second@example.com'])
        ->and($update['action_label'])->toBe('Edited')
        ->and($update)->not->toHaveKeys(['ip_address', 'user_agent']);
})->with([
    'reception' => [RoleName::Receptionist, UserType::MedicalStaff],
    'admin' => [RoleName::Admin, UserType::Admin],
]);

it('hides the patient event log from everyone else', function (RoleName $role) {
    activityActingAs($role);

    $this->getJson('/activity/patient_profile/'.activityPatient()->id)->assertForbidden();
})->with([RoleName::Doctor, RoleName::Nurse, RoleName::Pharmacist, RoleName::Cashier, RoleName::Accountant, RoleName::Support, RoleName::HrManager]);

it('follows each page: its own read right opens the log, the patient file right does not', function () {
    $id = (string) Str::uuid();

    actingWith('activity.read_all');
    $this->getJson("/activity/invoice/{$id}")->assertForbidden()->assertJsonPath('error_code', 'ACTIVITY_ENTITY_FORBIDDEN');

    actingWith('invoices.read_all');
    $this->getJson("/activity/invoice/{$id}")->assertOk()->assertJsonCount(0, 'data');

    actingWith('appointments.read_all');
    $this->getJson("/activity/appointment/{$id}")->assertOk();
    $this->getJson("/activity/patient_profile/{$id}")->assertForbidden();

    actingWith('appointments.read_own');
    $this->getJson("/activity/appointment/{$id}")->assertForbidden();
});

it('refuses an unknown record type and a signed-out caller', function () {
    actingWith('activity.read_all');
    $this->getJson('/activity/not_a_type/'.Str::uuid())->assertNotFound();

    $this->app['auth']->forgetGuards();
    $this->getJson('/activity/patient_profile/'.Str::uuid())->assertUnauthorized();
});

it('cannot be changed: no write route, and entries refuse updates and deletes', function () {
    actingWith('activity.read_all');
    $url = '/activity/patient_profile/'.activityPatient()->id;

    $this->postJson($url)->assertStatus(405);
    $this->putJson($url)->assertStatus(405);
    $this->deleteJson($url)->assertStatus(405);

    $entry = AuditLog::query()->firstOrFail();
    expect(fn () => $entry->forceFill(['module' => 'edited'])->save())->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
});

it('has an entity for every audited record type, each tied to a real right', function () {
    $aliases = array_keys(Config::array('audit.models'));
    $values = array_column(TrackedEntity::cases(), 'value');

    expect(array_diff($aliases, $values))->toBe([]);

    foreach (TrackedEntity::cases() as $entity) {
        if ($entity !== TrackedEntity::PatientProfile) {
            expect(Config::array('access.resources'))->toHaveKey((string) $entity->resource());
        }
    }
});

it('adds "opened the page" once per person, for real views only', function () {
    activityActingAs(RoleName::Receptionist);
    $person = activityPatient();
    $patientUrl = "/patients/{$person->id}";
    $viewed = fn () => AuditLog::query()->where('action', 'viewed')->where('auditable_id', $person->id)->count();

    $this->getJson($patientUrl)->assertOk();
    $this->getJson($patientUrl)->assertOk();
    expect($viewed())->toBe(1);

    $entry = collect($this->getJson("/activity/patient_profile/{$person->id}")->assertOk()->json('data'))->firstWhere('action', 'viewed');
    expect($entry['action_label'])->toBe('Opened the page')->and($entry['changed_by'])->not->toBeNull();

    $this->travel(6)->minutes();
    $this->getJson($patientUrl)->assertOk();
    expect($viewed())->toBe(2);

    $this->getJson('/patients/'.Str::uuid())->assertNotFound();
    expect(AuditLog::query()->where('action', 'viewed')->count())->toBe(2);
});

it('does not log opening a page the person may not open', function () {
    activityActingAs(RoleName::Support);
    $person = activityPatient();

    $this->getJson("/patients/{$person->id}")->assertForbidden();
    expect(AuditLog::query()->where('action', 'viewed')->count())->toBe(0);
});

it('maps every tracked page to a real route and record type', function () {
    $names = collect(app('router')->getRoutes()->getRoutes())->map->getName()->filter()->all();

    foreach (Config::array('audit.viewed_routes') as $route => $alias) {
        expect($names)->toContain($route)->and(Config::array('audit.models'))->toHaveKey($alias);
    }
});

it('shows a stored date without its empty time', function () {
    activityActingAs(RoleName::Receptionist);
    $person = Person::factory()->create(['date_of_birth' => '1980-01-02']);
    Patient::query()->forceCreate(['id' => $person->id]);
    $person->refresh()->update(['date_of_birth' => '1980-02-03']);

    $change = collect(collect($this->getJson("/activity/patient_profile/{$person->id}")->assertOk()->json('data'))->firstWhere('action', 'updated')['changes'])->firstWhere('field', 'date_of_birth');

    expect($change['new'])->toBe('1980-02-03');
});

it('describes only what happened: no field dump on create, no ids or encrypted values', function () {
    activityActingAs(RoleName::Receptionist);
    $person = Person::factory()->create(['email' => 'first@example.com']);
    Patient::query()->forceCreate(['id' => $person->id, 'emergency_contacts' => [['name' => 'Secret Name', 'relation' => 'Friend', 'phone' => '+97336550000']]]);
    $patient = Patient::query()->findOrFail($person->id);
    $patient->update(['emergency_contacts' => [['name' => 'Other Name', 'relation' => 'Friend', 'phone' => '+97336550001']]]);

    $response = $this->getJson("/activity/patient_profile/{$person->id}")->assertOk();
    $entries = collect($response->json('data'));

    expect($entries->where('action', 'created')->pluck('changes')->flatten(1)->all())->toBe([])
        ->and($response->getContent())->not->toContain('eyJpdiI6')->not->toContain('Secret Name')
        ->and($entries->pluck('changes')->flatten(1)->pluck('field')->all())->not->toContain('id');
});

it('never lists a staff id or a medical value without the right', function () {
    activityActingAs(RoleName::Receptionist);
    $person = Person::factory()->create();
    Patient::query()->forceCreate(['id' => $person->id]);
    $staffId = (string) Str::uuid();
    (new AuditLog)->forceFill([
        'id' => (string) Str::uuid7(), 'action' => 'updated', 'auditable_type' => 'patient', 'auditable_id' => $person->id, 'module' => 'appointments',
        'old_values' => ['blood_group' => 'A+', 'issued_by' => null, 'notes' => 'old'],
        'new_values' => ['blood_group' => 'B+', 'issued_by' => $staffId, 'notes' => 'new'],
        'created_at' => now(),
    ])->save();

    $response = $this->getJson("/activity/patient_profile/{$person->id}")->assertOk();
    $fields = collect($response->json('data'))->pluck('changes')->flatten(1)->pluck('field')->all();

    expect($fields)->toContain('notes')->not->toContain('blood_group')->not->toContain('issued_by')
        ->and($response->getContent())->not->toContain($staffId);
});

it('still loads the page when recording the view fails', function () {
    activityActingAs(RoleName::Receptionist);
    $person = activityPatient();
    Cache::shouldReceive('add')->andThrow(new RuntimeException('cache down'));

    $this->getJson("/patients/{$person->id}")->assertOk();
});
