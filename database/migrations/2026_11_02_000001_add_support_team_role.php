<?php

declare(strict_types=1);

use App\Enums\Identity\RoleName;
use App\Models\Identity\Permission;
use App\Models\Identity\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Support team system role (every ticket: take, answer, solve). A fresh install gets it from the roles seeder
 * after migrating; an existing database gets only this role here, so changes made to other roles are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        $guard = Config::string('access.guard');
        if (Role::query()->where('guard_name', $guard)->doesntExist()) {
            return;
        }
        Model::preventLazyLoading(false);

        /** @var array{access: array<string, list<string>>, actions: list<string>} $rights */
        $rights = Config::array('access.roles')[RoleName::Support->value];
        $names = $rights['actions'];
        foreach ($rights['access'] as $resource => $flags) {
            foreach ($flags as $flag) {
                $names[] = "{$resource}.{$flag}";
            }
        }

        $role = Role::query()->firstOrNew(['name' => RoleName::Support->value, 'guard_name' => $guard]);
        $role->fill(['display_name' => RoleName::Support->label(), 'is_system' => true])->save();
        $role->givePermissionTo(Permission::query()->where('guard_name', $guard)->whereIn('name', $names)->get());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()->where('name', RoleName::Support->value)->delete();
    }
};
