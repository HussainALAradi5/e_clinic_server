<?php

declare(strict_types=1);

use App\Models\Identity\Role;
use App\Models\Identity\User;
use App\Utilities\Shared\CacheKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\PermissionRegistrar;

/**
 * Existing databases: doctors, nurses and HR managers read people "own" instead of "all" — themself, every staff
 * member and the patients whose files they may open — so a doctor no longer lists other doctors' patients and HR
 * no longer sees or edits patients. Matches config/access.php for fresh installs.
 */
return new class extends Migration
{
    private const ROLES = ['doctor', 'nurse', 'hr_manager'];

    public function up(): void
    {
        $guard = Config::string('access.guard');
        // Spatie reloads a role's permissions after each change; fine in a one-off data step.
        Model::preventLazyLoading(false);
        foreach (Role::query()->where('guard_name', $guard)->whereIn('name', self::ROLES)->get() as $role) {
            if ($role->hasPermissionTo('people.read_all')) {
                $role->revokePermissionTo('people.read_all');
                $role->givePermissionTo('people.read_own');
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        User::query()->pluck('id')->each(fn (string $id) => Cache::forget(CacheKeys::permissions($id)));
    }

    public function down(): void
    {
        $guard = Config::string('access.guard');
        foreach (Role::query()->where('guard_name', $guard)->whereIn('name', self::ROLES)->get() as $role) {
            $role->givePermissionTo('people.read_all');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
