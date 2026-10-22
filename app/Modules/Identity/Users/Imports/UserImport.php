<?php

declare(strict_types=1);

namespace App\Modules\Identity\Users\Imports;

use App\Enums\Identity\RoleName;
use App\Enums\Identity\UserType;
use App\Modules\Identity\Roles\Models\Role;
use App\Modules\Identity\Users\Contracts\UserServiceInterface;
use App\Modules\Identity\Users\Data\CreateUserData;
use App\Modules\Identity\Users\Data\PersonData;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Transfer\Contracts\ImportDefinition;
use App\Shared\Transfer\ImportColumn;
use App\Shared\Transfer\ImportColumnType;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Staff accounts from Excel: the person columns of PersonImport plus account type and roles. Each account is
 * created through UserService (role-grant checks apply) and gets the "set your password" email once the whole
 * file is saved. Patient accounts are not imported — they come from patient registration.
 */
final class UserImport implements ImportDefinition
{
    public function __construct(
        private readonly UserServiceInterface $users,
        private readonly PersonImport $people,
    ) {}

    public function columns(): array
    {
        $types = [];
        foreach (self::staffTypes() as $type) {
            $types[$type->value] = $type->label();
        }

        $columns = array_map(
            fn (ImportColumn $c): ImportColumn => $c->field === 'email'
                ? new ImportColumn('email', 'Email', required: true, example: 'fatima@example.com', help: 'The sign-in email; the "set your password" link is sent here.')
                : $c,
            $this->people->columns(),
        );

        return [
            ...$columns,
            new ImportColumn('user_type', 'Account type', required: true, example: UserType::Receptionist->label(), type: ImportColumnType::Choice, options: $types),
            new ImportColumn('roles', 'Roles', required: true, example: RoleName::Receptionist->label(), help: 'One or more role names separated by commas, e.g. "Doctor, Cashier".'),
        ];
    }

    public function rules(): array
    {
        return [
            ...$this->people->rules(),
            'email' => ['required', 'email:rfc', 'max:255'],
            'user_type' => ['required', Rule::enum(UserType::class)->only(self::staffTypes())],
            'roles' => ['required', 'string', 'max:500'],
        ];
    }

    public function import(array $row, Authenticatable $user): Model
    {
        $person = $this->users->findOrCreatePerson(PersonData::fromArray($row));
        if (User::query()->whereKey($person->id)->exists()) {
            throw ValidationException::withMessages(['email' => __('errors.USER_ALREADY_HAS_ACCOUNT', ['code' => $person->code])]);
        }

        return $this->users->create($person, new CreateUserData(
            UserType::from((string) $row['user_type']),
            $this->roleNames((string) $row['roles']),
        ));
    }

    /** @return list<UserType> */
    private static function staffTypes(): array
    {
        return array_values(array_filter(UserType::cases(), fn (UserType $type): bool => ! $type->isPatient()));
    }

    /**
     * "Doctor, cashier" → role names (by name or display name, case-insensitive; the patient role is refused).
     *
     * @return list<string>
     */
    private function roleNames(string $cell): array
    {
        $roles = Role::query()->where('name', '!=', RoleName::Patient->value)->where('is_archived', false)->where('is_deleted', false)->get(['name', 'display_name']);
        $names = [];
        $unknown = [];

        foreach (array_filter(array_map(trim(...), explode(',', $cell))) as $wanted) {
            $role = $roles->first(fn (Role $r): bool => Str::lower($wanted) === Str::lower((string) $r->name) || Str::lower($wanted) === Str::lower((string) $r->display_name));
            $role === null ? $unknown[] = $wanted : $names[] = (string) $role->name;
        }

        if ($unknown !== [] || $names === []) {
            throw ValidationException::withMessages(['roles' => __('errors.IMPORT_UNKNOWN_ROLES', ['roles' => implode(', ', $unknown)])]);
        }

        return array_values(array_unique($names));
    }
}
