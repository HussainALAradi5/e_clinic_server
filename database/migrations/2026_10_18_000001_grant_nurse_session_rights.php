<?php

declare(strict_types=1);

use App\Enums\Identity\RoleName;
use App\Models\Identity\Role;
use App\Models\Identity\User;
use App\Utilities\Shared\CacheKeys;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\PermissionRegistrar;

/**
 * Nurses and therapists deliver treatment sessions (start / complete) and clinical orders: existing databases give
 * the nurse role `appointments.write`, as config/access.php now does for fresh installs. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::query()->where('guard_name', Config::string('access.guard'))->where('name', RoleName::Nurse->value)->first();
        if ($role === null) {
            return;
        }
        $role->givePermissionTo('appointments.write');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        User::query()->pluck('id')->each(fn (string $id) => Cache::forget(CacheKeys::permissions($id)));
    }

    public function down(): void
    {
        // Rights stay; removing them could take access away that an administrator granted on purpose.
    }
};
