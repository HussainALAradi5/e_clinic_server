<?php

declare(strict_types=1);

use App\Enums\HR\PayslipItemType;
use App\Enums\HR\SettingKey;
use App\Enums\HR\StaffRequestType;
use App\Enums\Shared\ApprovalStatus;
use App\Models\Identity\Permission;
use App\Models\Identity\Role;
use App\Utilities\Database\Constraints;
use App\Utilities\Shared\DocumentNumberGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * HR switches and staff requests:
 * - settings (SET-000001): one row per key — on / off and, for overtime, the pay per hour. Both start off.
 * - staff_requests (REQ-2026-000001): an employee asks HR to excuse a late arrival or to pay the overtime of one
 *   attendance day, with an optional reason; HR approves (reason optional) or rejects (reason required).
 * - payslip_items: a `lateness` line.
 * - attendances: late_excused (not deducted) and overtime_amount (priced when the overtime is approved).
 * Rights `settings.*` and `staff_requests.*` (+ review).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->string('key', 50)->unique();
            $table->boolean('is_enabled')->default(false);
            $table->money('amount')->nullable();
        });
        Constraints::enum('settings', 'key', SettingKey::class);
        Constraints::check('settings', 'chk_settings_amount', 'amount IS NULL OR amount >= 0');

        Schema::create('staff_requests', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->string('type', 20);
            $table->foreignUuid('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignUuid('attendance_id')->constrained('attendances')->restrictOnDelete();
            $table->string('reason', 1000)->nullable();
            $table->string('status', 20)->default(ApprovalStatus::Pending->value);
            $table->json('details')->nullable();
            $table->uuid('reviewed_by')->nullable()->index();
            $table->timestampTz('reviewed_at')->nullable();
            $table->string('review_reason', 1000)->nullable();
            $table->index(['employee_id', 'status']);
            $table->index(['attendance_id', 'type']);
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
        Constraints::enum('staff_requests', 'type', StaffRequestType::class);
        Constraints::enum('staff_requests', 'status', ApprovalStatus::class);

        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('late_excused')->default(false);
            $table->money('overtime_amount')->nullable();
        });

        // Payslips get a lateness line (late minutes not excused).
        Constraints::statement('ALTER TABLE payslip_items DROP CONSTRAINT IF EXISTS chk_payslip_items_type');
        Constraints::enum('payslip_items', 'type', PayslipItemType::class);

        DB::transaction(function (): void {
            foreach (SettingKey::cases() as $key) {
                DB::table('settings')->insert([
                    'id' => (string) Str::uuid7(),
                    'code' => app(DocumentNumberGenerator::class)->next('setting', 'SET', yearly: false),
                    'key' => $key->value, 'is_enabled' => false, 'amount' => null, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        $this->grantRights();
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['late_excused', 'overtime_amount']);
        });
        Schema::dropIfExists('staff_requests');
        Schema::dropIfExists('settings');
    }

    /** Existing databases: the new rights to the roles that have them in config/access.php. */
    private function grantRights(): void
    {
        $guard = Config::string('access.guard');
        if (Role::query()->where('guard_name', $guard)->doesntExist()) {
            return;
        }
        Model::preventLazyLoading(false);
        $names = ['settings.read_own', 'settings.read_all', 'settings.create', 'settings.write', 'settings.delete',
            'staff_requests.read_own', 'staff_requests.read_all', 'staff_requests.create', 'staff_requests.write', 'staff_requests.delete',
            'staff_requests.review', 'staff_requests.export'];
        foreach ($names as $name) {
            [$resource, $flag] = explode('.', $name);
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => $guard], ['module' => 'hr', 'description' => ucfirst(str_replace('_', ' ', $flag)).' — '.str_replace('_', ' ', $resource)]);
        }
        /** @var array<string, array{access: string|array<string, list<string>>, actions?: string|list<string>}> $roles */
        $roles = Config::array('access.roles');
        foreach (Role::query()->where('guard_name', $guard)->whereIn('name', array_keys($roles))->get() as $role) {
            $config = $roles[$role->name];
            if ($config['access'] === '*') {
                $role->givePermissionTo($names);

                continue;
            }
            $grant = [];
            foreach (['settings', 'staff_requests'] as $resource) {
                foreach ($config['access'][$resource] ?? [] as $flag) {
                    $grant[] = "{$resource}.{$flag}";
                }
            }
            $actions = $config['actions'] ?? [];
            if (is_array($actions)) {
                $grant = [...$grant, ...array_values(array_intersect($actions, $names))];
            }
            if ($grant !== []) {
                $role->givePermissionTo($grant);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
