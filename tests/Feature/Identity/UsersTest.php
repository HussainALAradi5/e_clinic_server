<?php

use App\Dtos\Identity\CreateUserData;
use App\Dtos\Identity\PersonData;
use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserStatus;
use App\Enums\Identity\UserType;
use App\Enums\Shared\Gender;
use App\Exceptions\Identity\DuplicatePersonException;
use App\Interfaces\Identity\UserServiceInterface;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Notifications\Identity\PasswordResetCodeNotification;
use App\Notifications\Identity\ResetPasswordNotification;
use App\Utilities\Shared\BlindIndex;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

function superAdmin(): User
{
    $user = User::factory()->withRole(RoleName::SuperAdmin->value)->withTwoFactor()->create();
    Sanctum::actingAs($user);

    return $user;
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('gives every person a running PRS code, a normalised phone and a blind-indexed encrypted national ID', function () {
    $person = Person::factory()->create(['phone' => '3600 1234', 'email' => ' Mixed@Example.COM ', 'national_id' => '880-1234 56']);
    $raw = DB::table('persons')->where('id', $person->id)->first();

    expect($person->code)->toBe('PRS-000001')
        ->and($person->phone)->toBe('+97336001234')
        ->and($person->email)->toBe('mixed@example.com')
        ->and($raw->national_id)->not->toContain('880')
        ->and($person->national_id_hash)->toBe(BlindIndex::make('88012345 6', 'national_id'))
        ->and(Person::factory()->create()->code)->toBe('PRS-000002');
});

it('lists users with filters and search, limited to the own account with read_own', function () {
    superAdmin();
    $doctor = User::factory()->type(UserType::Doctor)->create();
    User::factory()->status(UserStatus::Inactive)->create();

    $this->getJson('/api/v1/users?filter[user_type][eq]=doctor')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $doctor->id);
    $this->getJson('/api/v1/users?filter[status][eq]=inactive&sort=-last_name')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/users?search='.urlencode($doctor->person->last_name))->assertOk()->assertJsonPath('data.0.id', $doctor->id);
    $this->getJson('/api/v1/users?role=super_admin')->assertOk()->assertJsonCount(1, 'data');

    $self = actingWith('users.read_own');
    $this->getJson('/api/v1/users')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $self->id);
    $this->getJson("/api/v1/users/{$doctor->id}")->assertNotFound();
    $this->getJson("/api/v1/users/{$self->id}")->assertOk()->assertJsonPath('data.person.code', $self->person->code);

    actingWith('patients.read_all');
    $this->getJson('/api/v1/users')->assertForbidden();
});

it('changes account status with guards for self, the last super admin and a mandatory reason', function () {
    $admin = superAdmin();
    $user = User::factory()->create();
    $user->createToken('device');

    $this->patchJson("/api/v1/users/{$user->id}/status", ['status' => 'locked'])->assertStatus(422);
    $this->patchJson("/api/v1/users/{$user->id}/status", ['status' => 'locked', 'reason' => 'Left the clinic'])
        ->assertOk()->assertJsonPath('data.status', 'locked');
    expect($user->refresh()->locked_at)->not->toBeNull()->and($user->tokens()->count())->toBe(0);

    $this->patchJson("/api/v1/users/{$user->id}/status", ['status' => 'active'])->assertOk()->assertJsonPath('data.locked_at', null);
    $this->patchJson("/api/v1/users/{$admin->id}/status", ['status' => 'inactive', 'reason' => 'x'])
        ->assertStatus(409)->assertJsonPath('error_code', 'USER_CANNOT_MODIFY_SELF');

    $other = User::factory()->withRole(RoleName::Admin->value)->withTwoFactor()->create();
    grantPermissions($other, 'users.write', 'users.change_status');
    Sanctum::actingAs($other);
    $this->patchJson("/api/v1/users/{$admin->id}/status", ['status' => 'inactive', 'reason' => 'x'])
        ->assertStatus(409)->assertJsonPath('error_code', 'USER_LAST_SUPER_ADMIN');
});

it('searches people by name or exact national ID and reports duplicates', function () {
    superAdmin();
    $person = Person::factory()->withNationalId('123456789')->create(['first_name' => 'Fatima', 'phone' => '+97336000001', 'date_of_birth' => '1990-05-01']);
    Person::factory()->count(2)->create();

    $this->getJson('/api/v1/people?search=fatim')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.national_id', '*****6789');
    $this->getJson('/api/v1/people?national_id=1234-56789')->assertOk()->assertJsonPath('data.0.id', $person->id);

    $this->postJson('/api/v1/people/duplicates', ['phone' => '36000001', 'date_of_birth' => '1990-05-01'])->assertOk()->assertJsonCount(1, 'data');
    $this->postJson('/api/v1/people/duplicates', ['phone' => '36000001'])->assertOk()->assertJsonCount(0, 'data');
});

it('updates personal data with optimistic locking and duplicate / email checks', function () {
    superAdmin();
    $person = Person::factory()->create();
    $other = Person::factory()->withNationalId('999888777')->create();
    $user = User::factory()->create(['id' => $person->id]);

    $this->putJson("/api/v1/people/{$person->id}", ['version' => 1, 'first_name' => 'Ali', 'email' => 'new@example.com'])
        ->assertOk()->assertJsonPath('data.first_name', 'Ali')->assertJsonPath('data.version', 2);
    expect($user->refresh()->email_verified_at)->toBeNull();

    $this->putJson("/api/v1/people/{$person->id}", ['version' => 1, 'first_name' => 'Old'])->assertStatus(409)->assertJsonPath('error_code', 'CONCURRENT_MODIFICATION');
    $this->putJson("/api/v1/people/{$person->id}", ['version' => 2, 'email' => strtoupper((string) $other->email)])->assertStatus(409)->assertJsonPath('error_code', 'USER_EMAIL_TAKEN');
    $this->putJson("/api/v1/people/{$person->id}", ['version' => 2, 'national_id' => '999888777'])
        ->assertStatus(409)->assertJsonPath('error_code', 'PERSON_DUPLICATE')->assertJsonPath('meta.candidates.0.id', $other->id);
});

it('reuses people by national ID and creates accounts with an invitation when no password is set', function () {
    Notification::fake();
    $users = app(UserServiceInterface::class);
    $data = new PersonData(firstName: 'Sara', lastName: 'Ali', gender: Gender::Female, dateOfBirth: CarbonImmutable::parse('1985-02-03'), nationalId: '850203111', phone: '+97333333333', email: 'sara@example.com');

    $person = $users->findOrCreatePerson($data);
    expect($users->findOrCreatePerson($data)->id)->toBe($person->id);
    expect(fn () => $users->findOrCreatePerson(new PersonData(firstName: 'Sara', lastName: 'A', gender: Gender::Female, dateOfBirth: CarbonImmutable::parse('1985-02-03'), phone: '33333333')))
        ->toThrow(DuplicatePersonException::class);

    $user = $users->create($person, new CreateUserData(UserType::Receptionist, [RoleName::Receptionist->value]));
    expect($user->id)->toBe($person->id)->and($user->hasRole('receptionist'))->toBeTrue();
    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('publishes list schemas for users, people and audit without bulk actions', function () {
    superAdmin();

    $this->getJson('/api/v1/meta/resources/users')->assertOk()
        ->assertJsonPath('data.bulk_actions', [])
        ->assertJsonFragment(['field' => 'status', 'type' => 'enum', 'operators' => ['eq', 'in'], 'enum' => 'UserStatus']);
    $this->getJson('/api/v1/meta/resources/people')->assertOk()->assertJsonPath('data.bulk_actions', []);
    $this->getJson('/api/v1/meta/resources/audit')->assertOk()->assertJsonPath('data.default_sort', '-id');
    $this->getJson('/api/v1/meta/resources/roles')->assertOk()->assertJsonCount(4, 'data.bulk_actions');

    actingWith('users.read_own');
    $this->getJson('/api/v1/meta/resources/audit')->assertNotFound();
});

it('sends an admin-initiated reset code', function () {
    Notification::fake();
    superAdmin();
    $user = User::factory()->create();

    $this->postJson("/api/v1/users/{$user->id}/send-reset-link")->assertAccepted();
    Notification::assertSentTo($user, PasswordResetCodeNotification::class);
});

it('keeps one mobile number per account holder, in international format', function () {
    superAdmin();
    $holder = Person::factory()->create(['phone' => '+97336112233']);
    User::factory()->create(['id' => $holder->id]);
    $person = Person::factory()->create(['phone' => '+97339000001']);
    User::factory()->create(['id' => $person->id]);
    // A child without an account may share a parent's number.
    $child = Person::factory()->create(['phone' => '+97336112233']);

    $this->putJson("/api/v1/people/{$person->id}", ['version' => 1, 'phone' => '36112233'])
        ->assertStatus(409)->assertJsonPath('error_code', 'USER_PHONE_TAKEN');
    $this->putJson("/api/v1/people/{$person->id}", ['version' => 1, 'phone' => '12'])->assertUnprocessable()->assertJsonValidationErrors('phone');
    $this->putJson("/api/v1/people/{$person->id}", ['version' => 1, 'phone' => '+97336445566'])->assertOk()->assertJsonPath('data.phone', '+97336445566');
    expect($child->refresh()->phone)->toBe('+97336112233');
});
