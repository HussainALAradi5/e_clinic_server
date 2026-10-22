<?php

declare(strict_types=1);

namespace App\Modules\Identity\Roles\Imports;

use App\Enums\Identity\RoleName;
use App\Modules\Identity\Roles\Contracts\RoleServiceInterface;
use App\Modules\Identity\Roles\Data\RoleData;
use App\Modules\Identity\Roles\Http\Requests\StoreRoleRequest;
use App\Modules\Identity\Roles\Models\Role;
use App\Shared\Transfer\Contracts\ImportDefinition;
use App\Shared\Transfer\ImportColumn;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Custom roles from Excel: same rules as POST /roles, saved through RoleService (privilege checks apply). Rights
 * are copied from an existing role ("Based on") — a spreadsheet of permission names would be unreadable; fine-tune
 * them afterwards on the role's access matrix.
 */
final class RoleImport implements ImportDefinition
{
    public function __construct(private readonly RoleServiceInterface $roles) {}

    public function columns(): array
    {
        return [
            new ImportColumn('name', 'Key', required: true, example: 'senior_nurse', help: 'Lower-case letters, digits, - and _ only. Must be new.'),
            new ImportColumn('display_name', 'Display name', required: true, example: 'Senior nurse'),
            new ImportColumn('display_name_ar', 'Arabic display name', example: 'ممرض أول'),
            new ImportColumn('description', 'Description', example: 'Nurse with ward supervision rights'),
            new ImportColumn('based_on', 'Based on', example: RoleName::Nurse->label(), help: 'An existing role (key or name) whose rights are copied. Empty = no rights yet.'),
        ];
    }

    public function rules(): array
    {
        return [
            ...Arr::except(StoreRoleRequest::create('/', 'POST')->rules(), ['permissions', 'permissions.*']),
            'based_on' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function import(array $row, Authenticatable $user): Model
    {
        // Two rows with the same key: the second fails here instead of on the database constraint.
        if (Role::query()->where('name', $row['name'])->exists()) {
            throw ValidationException::withMessages(['name' => __('validation.unique', ['attribute' => 'Key'])]);
        }

        $permissions = [];
        if (filled($row['based_on'] ?? null)) {
            $wanted = Str::lower(trim((string) $row['based_on']));
            $source = Role::query()->with('permissions')->where('is_deleted', false)->get()
                ->first(fn (Role $role): bool => $wanted === Str::lower((string) $role->name) || $wanted === Str::lower((string) $role->display_name));
            if ($source === null) {
                throw ValidationException::withMessages(['based_on' => __('errors.IMPORT_UNKNOWN_ROLES', ['roles' => $row['based_on']])]);
            }
            $permissions = $source->permissions->pluck('name')->map(fn (mixed $name): string => (string) $name)->values()->all();
        }

        return $this->roles->createRole(RoleData::fromArray([...Arr::except($row, 'based_on'), 'permissions' => $permissions]));
    }
}
