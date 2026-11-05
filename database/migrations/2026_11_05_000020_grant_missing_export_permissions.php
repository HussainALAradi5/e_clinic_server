<?php

declare(strict_types=1);

use App\Models\Identity\Permission;
use App\Models\Identity\Role;
use App\Models\Identity\User;
use App\Utilities\Shared\CacheKeys;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\PermissionRegistrar;

/**
 * Existing databases: the journal, treatment, clinical-order and support-ticket lists offered an export nobody held.
 * Adds those rights and grants them as config/access.php does for fresh installs. Additive only — nothing is revoked.
 */
return new class extends Migration
{
    private const NEW = ['journal.export', 'treatments.export', 'orders.export', 'tickets.export'];

    public function up(): void
    {
        $guard = Config::string('access.guard');
        foreach (self::NEW as $name) {
            [$resource, $action] = explode('.', $name);
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => $guard],
                ['module' => Config::string("access.resources.{$resource}.module"), 'description' => ucfirst($action).' — '.$resource],
            );
        }

        /** @var array<string, array{actions: string|list<string>, except_actions?: list<string>}> $defaults */
        $defaults = Config::array('access.roles');
        foreach (Role::query()->with('permissions')->where('guard_name', $guard)->whereIn('name', array_keys($defaults))->get() as $role) {
            $actions = $defaults[$role->name]['actions'];
            $grant = $actions === '*' ? self::NEW : array_values(array_intersect(self::NEW, $actions));
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
