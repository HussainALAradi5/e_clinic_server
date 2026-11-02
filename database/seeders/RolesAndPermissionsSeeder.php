<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Identity\RoleName;
use App\Models\Identity\Permission;
use App\Models\Identity\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions `<resource>.<flag|action>` for every resource in config/access.php and the system roles
 * with their default access matrix. Idempotent: re-running adds new permissions and resets system roles.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $guard = Config::string('access.guard');
        /** @var array<string, array{module: string, actions: list<string>, ownership: string}> $resources */
        $resources = Config::array('access.resources');

        $all = [];
        foreach ($resources as $resource => $config) {
            foreach ([...Permission::FLAGS, ...$config['actions']] as $ability) {
                $name = "{$resource}.{$ability}";
                Permission::query()->updateOrCreate(
                    ['name' => $name, 'guard_name' => $guard],
                    ['module' => $config['module'], 'description' => str_replace('_', ' ', ucfirst($ability)).' — '.str_replace('_', ' ', $resource)],
                );
                $all[] = $name;
            }
        }

        /** @var array<string, array{access: string|array<string, list<string>>, actions: string|list<string>, except_actions?: list<string>}> $roles */
        $roles = Config::array('access.roles');

        foreach (RoleName::cases() as $roleName) {
            $role = Role::query()->firstOrNew(['name' => $roleName->value, 'guard_name' => $guard]);
            $role->fill(['display_name' => $roleName->label(), 'is_system' => true])->save();

            $role->syncPermissions(Permission::query()->where('guard_name', $guard)
                ->whereIn('name', $this->permissionsFor($roles[$roleName->value] ?? ['access' => [], 'actions' => []], $resources, $all))
                ->get());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  array{access: string|array<string, list<string>>, actions: string|list<string>, except_actions?: list<string>}  $role
     * @param  array<string, array{module: string, actions: list<string>, ownership: string}>  $resources
     * @param  list<string>  $all
     * @return list<string>
     */
    private function permissionsFor(array $role, array $resources, array $all): array
    {
        $names = [];

        if (is_array($role['access'])) {
            foreach ($role['access'] as $resource => $flags) {
                // read_all implies read_own.
                $flags = in_array('read_all', $flags, true) ? [...$flags, 'read_own'] : $flags;
                foreach ($flags as $flag) {
                    $names[] = "{$resource}.{$flag}";
                }
            }
        } else {
            foreach (array_keys($resources) as $resource) {
                foreach (Permission::flags() as $flag) {
                    $names[] = "{$resource}.{$flag}";
                }
            }
        }

        $names = [...$names, ...(is_array($role['actions'])
            ? $role['actions']
            : array_filter($all, fn (string $name): bool => ! in_array(explode('.', $name, 2)[1], Permission::FLAGS, true)))];

        return array_values(array_diff(array_unique($names), $role['except_actions'] ?? []));
    }
}
