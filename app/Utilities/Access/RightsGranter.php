<?php

declare(strict_types=1);

namespace App\Utilities\Access;

use App\Models\Identity\Permission;
use App\Models\Identity\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\PermissionRegistrar;

/**
 * For migrations that add a resource to config/access.php on existing databases: creates its permissions and
 * grants each role what the config gives it (CRUD flags under `access`, special actions under `actions`).
 */
final class RightsGranter
{
    /**
     * @param  list<string>  $flags  CRUD flags and special actions of the resource (e.g. read_all, waive)
     */
    public static function grant(string $resource, array $flags, string $module, string $label): void
    {
        $guard = Config::string('access.guard');
        if (Role::query()->where('guard_name', $guard)->doesntExist()) {
            return;
        }
        Model::preventLazyLoading(false);
        foreach ($flags as $flag) {
            Permission::query()->firstOrCreate(
                ['name' => "{$resource}.{$flag}", 'guard_name' => $guard],
                ['module' => $module, 'description' => ucfirst(str_replace('_', ' ', $flag)).' — '.$label],
            );
        }
        /** @var array<string, array{access: string|array<string, list<string>>, actions?: string|list<string>}> $roles */
        $roles = Config::array('access.roles');
        foreach (Role::query()->where('guard_name', $guard)->whereIn('name', array_keys($roles))->get() as $role) {
            $config = $roles[$role->name];
            if ($config['access'] === '*') {
                $role->givePermissionTo(array_map(fn (string $f): string => "{$resource}.{$f}", $flags));

                continue;
            }
            $actions = is_array($config['actions'] ?? null) ? $config['actions'] : [];
            $grant = [
                ...array_map(fn (string $f): string => "{$resource}.{$f}", $config['access'][$resource] ?? []),
                ...array_values(array_filter($actions, fn (string $a): bool => str_starts_with($a, "{$resource}."))),
            ];
            if ($grant !== []) {
                $role->givePermissionTo($grant);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
