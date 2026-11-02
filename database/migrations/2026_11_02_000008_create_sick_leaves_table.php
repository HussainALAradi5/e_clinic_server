<?php

declare(strict_types=1);

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
 * Sick leave certificates (SLV-2026-000001): issued by the doctor at a visit for the patient — the days off, an
 * optional reason printed on the certificate. Cancelled with a reason, never deleted. Rights `sick_leaves.*`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sick_leaves', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->foreignUuid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignUuid('doctor_id')->constrained('employees')->restrictOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days');
            // Encrypted; printed only when show_reason (else the certificate says "medical reasons").
            $table->text('reason')->nullable();
            $table->boolean('show_reason')->default(false);
            $table->string('notes', 500)->nullable();
            $table->timestampTz('issued_at');
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->index(['patient_id', 'start_date']);
            $table->index(['doctor_id', 'start_date']);
        });
        Constraints::check('sick_leaves', 'chk_sick_leaves_dates', 'end_date >= start_date AND days > 0');

        $this->grantRights();
    }

    public function down(): void
    {
        Schema::dropIfExists('sick_leaves');
    }

    /** Existing databases: `sick_leaves.*` to the roles that have them in config/access.php. */
    private function grantRights(): void
    {
        $guard = Config::string('access.guard');
        if (Role::query()->where('guard_name', $guard)->doesntExist()) {
            return;
        }
        Model::preventLazyLoading(false);
        $names = ['sick_leaves.read_own', 'sick_leaves.read_all', 'sick_leaves.create', 'sick_leaves.write', 'sick_leaves.delete', 'sick_leaves.export'];
        foreach ($names as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => $guard], ['module' => 'appointments', 'description' => ucfirst(str_replace('_', ' ', explode('.', $name)[1])).' — sick leaves']);
        }
        /** @var array<string, array{access: string|array<string, list<string>>}> $roles */
        $roles = Config::array('access.roles');
        foreach (Role::query()->where('guard_name', $guard)->whereIn('name', array_keys($roles))->get() as $role) {
            $access = $roles[$role->name]['access'];
            $grant = $access === '*' ? $names : array_map(fn (string $flag): string => 'sick_leaves.'.$flag, $access['sick_leaves'] ?? []);
            if ($grant !== []) {
                $role->givePermissionTo($grant);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
