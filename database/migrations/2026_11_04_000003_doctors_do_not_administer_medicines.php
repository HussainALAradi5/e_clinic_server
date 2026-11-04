<?php

declare(strict_types=1);

use App\Models\Identity\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\PermissionRegistrar;

/**
 * Doctors prescribe; they do not give medicines out. Existing databases: `dispensing.administer` leaves the doctor
 * role (nurses administer in the treatment room, the pharmacy dispenses).
 */
return new class extends Migration
{
    public function up(): void
    {
        $doctor = Role::query()->where('guard_name', Config::string('access.guard'))->where('name', 'doctor')->first();
        if ($doctor?->hasPermissionTo('dispensing.administer')) {
            $doctor->revokePermissionTo('dispensing.administer');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Role::query()->where('guard_name', Config::string('access.guard'))->where('name', 'doctor')->first()
            ?->givePermissionTo('dispensing.administer');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
