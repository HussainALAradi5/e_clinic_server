<?php

declare(strict_types=1);

use App\Enums\Identity\RoleName;
use App\Modules\Identity\Roles\Models\Permission;
use App\Modules\Identity\Roles\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\PermissionRegistrar;

/**
 * `patients.view_history` (health, surgical and family history, past visit notes) for doctors and the admin roles;
 * the nurse role narrowed to her own patients, appointments, treatments, records and consents. Existing databases
 * only — a fresh install gets the same from the roles seeder.
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
        $history = Permission::query()->firstOrCreate(
            ['name' => 'patients.view_history', 'guard_name' => $guard],
            ['module' => 'appointments', 'description' => 'View history — patients'],
        );
        Role::query()->where('guard_name', $guard)
            ->whereIn('name', [RoleName::SuperAdmin->value, RoleName::Admin->value, RoleName::Doctor->value])
            ->get()->each(fn (Role $role) => $role->givePermissionTo($history));

        $nurse = Role::query()->where('guard_name', $guard)->where('name', RoleName::Nurse->value)->first();
        if ($nurse !== null) {
            foreach (['consents', 'patients', 'appointments', 'treatments', 'records'] as $resource) {
                $nurse->revokePermissionTo(Permission::query()->where('guard_name', $guard)->where('name', "{$resource}.read_all")->get());
                $nurse->givePermissionTo(Permission::query()->where('guard_name', $guard)->where('name', "{$resource}.read_own")->get());
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'patients.view_history')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
