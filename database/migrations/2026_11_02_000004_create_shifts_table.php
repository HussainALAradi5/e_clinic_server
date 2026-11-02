<?php

declare(strict_types=1);

use App\Enums\HR\ShiftType;
use App\Models\Identity\Permission;
use App\Models\Identity\Role;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Work shifts (SFT-…): fixed hours, or flexible (a window, an arrive-by time, hours due). Each employee is on at most
 * one shift; attendance measures late / on time / overtime against it. HR and admins manage them (`shifts.*`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->string('name', 100);
            $table->string('type', 10);
            // Fixed: start and end. Flexible: the window (earliest in, latest out).
            $table->time('start_time');
            $table->time('end_time');
            // Flexible only: arrive by this time.
            $table->time('latest_start')->nullable();
            // Hours due a day (fixed: end − start).
            $table->unsignedSmallInteger('required_minutes');
            $table->string('notes', 500)->nullable();
        });
        Constraints::enum('shifts', 'type', ShiftType::class);
        Constraints::check('shifts', 'chk_shifts_window', 'end_time > start_time');
        Constraints::check('shifts', 'chk_shifts_flexible', "type = 'fixed' OR latest_start IS NOT NULL");

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignUuid('shift_id')->nullable()->after('department_id')->constrained('shifts')->nullOnDelete();
        });

        $this->grantRights();
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });
        Schema::dropIfExists('shifts');
    }

    /** Existing databases: the shifts.* permissions, given to the roles that have them in config/access.php. */
    private function grantRights(): void
    {
        $guard = Config::string('access.guard');
        if (Role::query()->where('guard_name', $guard)->doesntExist()) {
            return;
        }
        Model::preventLazyLoading(false);
        $names = ['shifts.read_own', 'shifts.read_all', 'shifts.create', 'shifts.write', 'shifts.delete', 'shifts.export'];
        foreach ($names as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => $guard], ['module' => 'hr', 'description' => ucfirst(str_replace('_', ' ', explode('.', $name)[1])).' — shifts']);
        }
        /** @var array<string, array{access: string|array<string, list<string>>, actions: string|list<string>}> $roles */
        $roles = Config::array('access.roles');
        foreach (Role::query()->where('guard_name', $guard)->whereIn('name', array_keys($roles))->get() as $role) {
            $default = $roles[$role->name];
            $grant = $default['access'] === '*'
                ? $names
                : array_map(fn (string $flag): string => 'shifts.'.$flag, $default['access']['shifts'] ?? []);
            if ($grant !== []) {
                $role->givePermissionTo($grant);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
