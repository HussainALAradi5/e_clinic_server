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

it('shows who changed what on the patient file to reception, HR and admins', function (RoleName $role, UserType $type) {
    activityActingAs($role, $type);
    $person = activityPatient();

    $entries = $this->getJson("/activity/patient_profile/{$person->id}")->assertOk()->json('data');
    $update = collect($entries)->firstWhere('action', 'updated');

    expect($update['changes'])->toContain(['field' => 'email', 'label' => 'Email', 'old' => 'first@example.com', 'new' => 'second@example.com'])
        ->and($update['action_label'])->toBe('Edited')
        ->and($update)->not->toHaveKeys(['ip_address', 'user_agent']);
})->with([
    'reception' => [RoleName::Receptionist, UserType::MedicalStaff],
    'hr' => [RoleName::HrManager, UserType::Hr],
    'admin' => [RoleName::Admin, UserType::Admin],
]);

it('hides the patient event log from everyone else', function (RoleName $role) {
    activityActingAs($role);

    $this->getJson('/activity/patient_profile/'.activityPatient()->id)->assertForbidden();
})->with([RoleName::Doctor, RoleName::Nurse, RoleName::Pharmacist, RoleName::Cashier, RoleName::Accountant, RoleName::Support]);

it('also needs the right to read that kind of record', function () {
    $id = (string) Str::uuid();

    actingWith('activity.read_all');
    $this->getJson("/activity/invoice/{$id}")->assertForbidden()->assertJsonPath('error_code', 'ACTIVITY_ENTITY_FORBIDDEN');

    actingWith('activity.read_all', 'invoices.read_all');
    $this->getJson("/activity/invoice/{$id}")->assertOk()->assertJsonCount(0, 'data');
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
