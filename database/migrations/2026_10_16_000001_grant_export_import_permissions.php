<?php

declare(strict_types=1);

use App\Modules\Identity\Roles\Models\Permission;
use App\Modules\Identity\Roles\Models\Role;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Support\CacheKeys;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\PermissionRegistrar;

/**
 * Existing databases: adds the `<resource>.export` / `<resource>.import` permissions of config/access.php and grants
 * them to the system roles as their defaults say (super admin / admin: all). Additive only — nothing is revoked, and
 * rights edited in the access matrix stay as they are. Fresh installs get them from RolesAndPermissionsSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        $guard = Config::string('access.guard');

        /** @var array<string, array{module: string, actions: list<string>}> $resources */
        $resources = Config::array('access.resources');
        $new = [];
        foreach ($resources as $resource => $config) {
            foreach (array_intersect($config['actions'], ['export', 'import']) as $action) {
                Permission::query()->firstOrCreate(
                    ['name' => "{$resource}.{$action}", 'guard_name' => $guard],
                    ['module' => $config['module'], 'description' => ucfirst($action).' — '.str_replace('_', ' ', $resource)],
                );
                $new[] = "{$resource}.{$action}";
            }
        }

        /** @var array<string, array{actions: string|list<string>, except_actions?: list<string>}> $defaults */
        $defaults = Config::array('access.roles');
        foreach (Role::query()->where('guard_name', $guard)->whereIn('name', array_keys($defaults))->get() as $role) {
            $actions = $defaults[$role->name]['actions'];
            $grant = $actions === '*' ? $new : array_values(array_intersect($new, $actions));
            $grant = array_values(array_diff($grant, $defaults[$role->name]['except_actions'] ?? []));
            if ($grant !== []) {
                $role->givePermissionTo($grant);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        User::query()->pluck('id')->each(fn (string $id) => Cache::forget(CacheKeys::permissions($id)));
    }

    public function down(): void
    {
        // Rights stay; removing them could take access away that an administrator granted on purpose.
    }
};
