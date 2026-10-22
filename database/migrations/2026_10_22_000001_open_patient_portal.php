<?php

declare(strict_types=1);

use App\Enums\Identity\RoleName;
use App\Modules\Identity\Roles\Models\Permission;
use App\Modules\Identity\Roles\Models\Role;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Support\CacheKeys;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\PermissionRegistrar;

/**
 * Existing databases, as config does for fresh installs: patients may show the QR code of their own bookings and
 * prescriptions in the portal (`codes.read_own`, `codes.generate`). Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $guard = Config::string('access.guard');
        $role = Role::query()->where('guard_name', $guard)->where('name', RoleName::Patient->value)->first();
        if ($role === null) {
            return;
        }
        $role->givePermissionTo(array_map(
            fn (string $name) => Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => $guard], ['module' => 'identity']),
            ['codes.read_own', 'codes.generate'],
        ));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        User::query()->where('user_type', 'patient')->pluck('id')->each(fn (string $id) => Cache::forget(CacheKeys::permissions($id)));
    }

    public function down(): void
    {
        // Rights stay; removing them could break access granted on purpose.
    }
};
