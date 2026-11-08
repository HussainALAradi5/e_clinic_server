<?php

declare(strict_types=1);

use App\Models\Identity\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\PermissionRegistrar;

/** Staff roles reach a file through its owner record. Existing databases only (a fresh install gets this from its seeder). */
return new class extends Migration
{
    private const ALL = 'attachments.read_all';

    private const OWN = 'attachments.read_own';

    private const ROLES = ['receptionist', 'doctor', 'nurse', 'pharmacist', 'hr_manager', 'accountant'];

    public function up(): void
    {
        $roles = Role::query()->where('guard_name', Config::string('access.guard'))->whereIn('name', self::ROLES)->get();

        foreach ($roles as $role) {
            if ($role->permissions->contains('name', self::ALL)) {
                $role->revokePermissionTo(self::ALL);
                $role->givePermissionTo(self::OWN);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Rights stay: restoring the wider one could undo a choice an administrator made by hand.
    }
};
