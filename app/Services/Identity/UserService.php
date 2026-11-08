<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Dtos\Identity\CreateUserData;
use App\Dtos\Identity\PersonData;
use App\Enums\Identity\UserStatus;
use App\Events\Identity\UserCreated;
use App\Events\Identity\UserStatusChanged;
use App\Exceptions\Identity\CannotModifySelfException;
use App\Exceptions\Identity\DuplicatePersonException;
use App\Exceptions\Identity\EmailAlreadyTakenException;
use App\Exceptions\Identity\LastSuperAdminException;
use App\Exceptions\Identity\PhoneAlreadyTakenException;
use App\Interfaces\Identity\AuthServiceInterface;
use App\Interfaces\Identity\UserServiceInterface;
use App\Interfaces\Reference\AddressServiceInterface;
use App\Models\Identity\PasswordHistory;
use App\Models\Identity\Person;
use App\Models\Identity\TrustedDevice;
use App\Models\Identity\User;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\BlindIndex;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\PhoneNumber;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UserService implements UserServiceInterface
{
    private const DUPLICATE_LIMIT = 10;

    public function __construct(
        private readonly ApplyListQuery $listQuery,
        private readonly AuthServiceInterface $auth,
        private readonly PrivilegeGuard $privileges,
        private readonly ClinicClock $clock,
        private readonly AddressServiceInterface $addresses,
    ) {}

    public function searchPeople(ListQuery $query, ?string $nationalId = null): LengthAwarePaginator|CursorPaginator
    {
        $builder = OwnershipScope::apply(Person::query()->with(['user', 'nationality']), 'people', Auth::user());

        if (filled($nationalId)) {
            $builder->where('national_id_hash', BlindIndex::make((string) $nationalId, Person::BLIND_INDEX_CONTEXT));
        }

        return $this->listQuery->paginate($builder, $query);
    }

    public function findDuplicates(PersonData $data, ?string $exceptId = null): Collection
    {
        $byNationalId = filled($data->nationalId);
        $byPhoneAndBirth = filled($data->phone) && $data->dateOfBirth !== null;

        if (! $byNationalId && ! $byPhoneAndBirth) {
            return new Collection;
        }

        return Person::query()
            ->when($exceptId !== null, fn (Builder $q) => $q->whereKeyNot($exceptId))
            ->where(function (Builder $q) use ($data, $byNationalId, $byPhoneAndBirth): void {
                if ($byNationalId) {
                    $q->orWhere('national_id_hash', BlindIndex::make((string) $data->nationalId, Person::BLIND_INDEX_CONTEXT));
                }
                if ($byPhoneAndBirth) {
                    $q->orWhere(fn (Builder $w) => $w
                        ->where('phone', PhoneNumber::normalise((string) $data->phone))
                        ->whereDate('date_of_birth', $data->dateOfBirth?->toDateString()));
                }
            })
            ->limit(self::DUPLICATE_LIMIT)
            ->get()
            ->toBase();
    }

    public function findOrCreatePerson(PersonData $data, bool $force = false): Person
    {
        if (filled($data->nationalId)) {
            $existing = Person::query()
                ->where('national_id_hash', BlindIndex::make((string) $data->nationalId, Person::BLIND_INDEX_CONTEXT))
                ->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        $candidates = $this->findDuplicates($data);
        if ($candidates->isNotEmpty() && ! ($force && (Auth::user()?->can('patients.force_create') ?? false))) {
            throw $this->duplicate($candidates);
        }

        $this->assertEmailAvailable($data->email);

        return DB::transaction(function () use ($data): Person {
            $person = Person::query()->create($data->attributes());
            if ($data->address !== null) {
                $this->saveAddress($person, $data);
                $person->save();
            }

            return $person;
        });
    }

    public function updatePerson(Person $person, PersonData $data, int $version): Person
    {
        return DB::transaction(function () use ($person, $data, $version): Person {
            $person = Person::query()->lockForUpdate()->whereKey($person->id)->firstOrFail();
            $person->assertVersion($version);
            $person->fill($data->attributes());

            if ($person->isDirty(['national_id', 'phone', 'date_of_birth'])) {
                $candidates = $this->findDuplicates(new PersonData(
                    dateOfBirth: $person->date_of_birth,
                    nationalId: $person->national_id,
                    phone: $person->phone,
                ), $person->id);

                if ($candidates->isNotEmpty()) {
                    throw $this->duplicate($candidates);
                }
            }

            if ($person->isDirty('phone') && $person->user !== null) {
                $this->assertPhoneAvailable($person->phone, $person->id);
            }

            if ($person->isDirty('email')) {
                $this->assertEmailAvailable($person->email, $person->id);
                // A changed login email must be verified again.
                $person->user?->forceFill(['email_verified_at' => null])->save();
            }

            if ($data->addressProvided()) {
                $this->saveAddress($person, $data);
            }

            $person->save();

            return $person->refresh()->load(['user', 'nationality', 'address.country']);
        });
    }

    public function list(ListQuery $query, ?string $role = null, ?string $withoutRole = null): LengthAwarePaginator|CursorPaginator
    {
        $builder = $this->users()
            ->when(filled($role), fn (Builder $q) => $q->whereHas('roles', fn (Builder $r) => $r->where('name', $role)))
            ->when(filled($withoutRole), fn (Builder $q) => $q->whereDoesntHave('roles', fn (Builder $r) => $r->where('name', $withoutRole)));

        return $this->listQuery->paginate($builder, $query);
    }

    public function find(string $id): User
    {
        return $this->users()->with(['person.nationality', 'person.address.country'])->findOrFail($id);
    }

    public function create(Person $person, CreateUserData $data): User
    {
        // An active account signs in with its email; staff who never log in get an inactive account without one.
        if (blank($person->email) && $data->status === UserStatus::Active) {
            throw ValidationException::withMessages(['email' => __('validation.required', ['attribute' => 'email'])]);
        }
        if (User::query()->whereKey($person->id)->exists()) {
            throw new EmailAlreadyTakenException;
        }
        $this->assertPhoneAvailable($person->phone, $person->id);

        if (! $data->isRoleFixedBySystem) {
            $this->privileges->assertCanGrantRoles($this->actor(), $data->roles);
        }

        $user = DB::transaction(function () use ($person, $data): User {
            $user = User::query()->create([
                'id' => $person->id,
                'user_type' => $data->userType,
                'status' => $data->status,
                'password' => $data->password,
            ]);

            if ($data->password !== null) {
                PasswordHistory::remember($user);
            }

            $user->syncRoles($data->roles);

            return $user->setRelation('person', $person);
        });

        UserCreated::dispatch($user, $data->password === null && $data->status === UserStatus::Active);

        return $user;
    }

    public function changeStatus(User $user, UserStatus $status, ?string $reason = null): User
    {
        $from = $user->status;
        if ($from === $status) {
            return $user;
        }

        if ($user->is($this->actor())) {
            throw new CannotModifySelfException;
        }

        if ($status !== UserStatus::Active && $from === UserStatus::Active && $user->isSuperAdmin() && User::activeSuperAdminCount() <= 1) {
            throw new LastSuperAdminException;
        }

        DB::transaction(function () use ($user, $status): void {
            $user->forceFill([
                'status' => $status,
                'locked_at' => $status === UserStatus::Locked ? $this->clock->now() : null,
            ])->save();

            if (in_array($status, [UserStatus::Inactive, UserStatus::Locked], true)) {
                $user->tokens()->delete();
                TrustedDevice::forget($user);
            }
        });

        if ($from === UserStatus::Locked && $status === UserStatus::Active) {
            $this->auth->clearLoginThrottle($user);
        }

        UserStatusChanged::dispatch($user, $from, $reason);

        return $user->refresh()->load('person', 'roles');
    }

    public function sendPasswordResetLink(User $user): void
    {
        $this->auth->forgotPassword((string) $user->email());
    }

    /**
     * Accounts of living persons, limited to the caller's own account with `users.read_own` only.
     *
     * @return Builder<User>
     */
    private function users(): Builder
    {
        $query = User::query()
            ->select('users.*')
            ->join('persons', 'persons.id', '=', 'users.id')
            ->where('persons.is_deleted', false)
            ->with(['person', 'roles']);

        return OwnershipScope::apply($query, 'users', Auth::user());
    }

    /** One mobile number per account holder (people without an account, e.g. children, may share a parent's). */
    private function assertPhoneAvailable(string $phone, string $exceptId): void
    {
        $taken = Person::query()
            ->where('phone', PhoneNumber::normalise($phone))
            ->whereKeyNot($exceptId)
            ->where('is_deleted', false)
            ->whereHas('user')
            ->exists();

        if ($taken) {
            throw new PhoneAlreadyTakenException;
        }
    }

    private function assertEmailAvailable(?string $email, ?string $exceptId = null): void
    {
        if (blank($email)) {
            return;
        }

        $taken = Person::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim((string) $email))])
            ->when($exceptId !== null, fn (Builder $q) => $q->whereKeyNot($exceptId))
            ->exists();

        if ($taken) {
            throw new EmailAlreadyTakenException;
        }
    }

    /**
     * @param  Collection<int, Person>  $candidates
     */
    private function duplicate(Collection $candidates): DuplicatePersonException
    {
        return new DuplicatePersonException(context: ['candidates' => $candidates->map(fn (Person $p): array => [
            'id' => $p->id,
            'code' => $p->code,
            'full_name' => $p->full_name,
            'date_of_birth' => $p->date_of_birth->toDateString(),
            'phone' => PhoneNumber::mask($p->phone),
        ])->values()->all()]);
    }

    private function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /** Creates, replaces or removes the person's own address (never shared with another record). */
    private function saveAddress(Person $person, PersonData $data): void
    {
        if ($data->address === null) {
            $old = $person->address;
            $person->address_id = null;
            $person->save();
            $this->addresses->remove($old);

            return;
        }

        $person->address_id = $this->addresses->save($person->address, $data->address)->id;
    }
}
