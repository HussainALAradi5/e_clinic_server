<?php

declare(strict_types=1);

namespace App\Modules\Identity\Users\Contracts;

use App\Enums\Identity\UserStatus;
use App\Modules\Identity\Users\Data\CreateUserData;
use App\Modules\Identity\Users\Data\PersonData;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserServiceInterface
{
    /**
     * Search all persons by name, code, phone or email; exact national ID through the blind index.
     *
     * @return LengthAwarePaginator<int, Person>|CursorPaginator<int, Person>
     */
    public function searchPeople(ListQuery $query, ?string $nationalId = null): LengthAwarePaginator|CursorPaginator;

    /**
     * Candidates with the same national ID, or the same phone + date of birth.
     *
     * @return Collection<int, Person>
     */
    public function findDuplicates(PersonData $data, ?string $exceptId = null): Collection;

    /** **Internal** — used by PatientService::create and EmployeeService::create. */
    public function findOrCreatePerson(PersonData $data, bool $force = false): Person;

    /** Edits personal data once — visible everywhere (patient file, HR, user). */
    public function updatePerson(Person $person, PersonData $data, int $version): Person;

    /**
     * Users (accounts) by type, status, role; joined with the person's name and email.
     *
     * @return LengthAwarePaginator<int, User>|CursorPaginator<int, User>
     */
    public function list(ListQuery $query, ?string $role = null, ?string $withoutRole = null): LengthAwarePaginator|CursorPaginator;

    /** Account with person and roles. */
    public function find(string $id): User;

    /** **Internal** — adds a users row to an existing person (same id). */
    public function create(Person $person, CreateUserData $data): User;

    /** Activates, deactivates, locks or unlocks an account. */
    public function changeStatus(User $user, UserStatus $status, ?string $reason = null): User;

    /** Admin-initiated reset email (admins never see or set other users' passwords). */
    public function sendPasswordResetLink(User $user): void;
}
