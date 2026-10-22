<?php

declare(strict_types=1);

use App\Enums\Identity\RoleName;
use App\Modules\Accounting\Accounts\Models\Account;
use App\Modules\HR\Employees\Models\Employee;
use App\Modules\Identity\Roles\Models\Permission;
use App\Modules\Identity\Roles\Models\Role;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Support\CacheKeys;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\LeaveTypeSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\PermissionRegistrar;

/**
 * Existing databases, as config does for fresh installs: staff who may request leave can also cancel their own
 * requests (`leaves.write`, limited to their own rows) and cashiers can attach a sick-leave certificate; HR and finance get the attendance / leave / payroll list
 * exports; the chart gains the payroll deductions account and the default leave types are added. Additive only — nothing is revoked or renamed.
 */
return new class extends Migration
{
    private const EXPORTS = ['attendance.export', 'leaves.export', 'payroll.export'];

    public function up(): void
    {
        $guard = Config::string('access.guard');
        $write = Permission::query()->firstOrCreate(['name' => 'leaves.write', 'guard_name' => $guard], ['module' => 'hr', 'description' => 'Write — leaves']);
        $requesters = Permission::query()->where('guard_name', $guard)->where('name', 'leaves.create')->first()->roles ?? collect();
        $requesters->each(fn ($role) => $role->givePermissionTo($write));

        Role::query()->where('guard_name', $guard)->where('name', RoleName::Cashier->value)->first()
            ?->givePermissionTo(array_map(fn (string $n) => Permission::query()->firstOrCreate(['name' => $n, 'guard_name' => $guard], ['module' => 'identity']), ['attachments.read_own', 'attachments.create']));

        foreach (self::EXPORTS as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => $guard], ['module' => 'hr', 'description' => 'Export — '.explode('.', $name)[0]]);
        }
        /** @var array<string, array{actions: string|list<string>}> $defaults */
        $defaults = Config::array('access.roles');
        foreach (Role::query()->where('guard_name', $guard)->whereIn('name', array_keys($defaults))->get() as $role) {
            $actions = $defaults[$role->name]['actions'];
            $grant = $actions === '*' ? self::EXPORTS : array_values(array_intersect(self::EXPORTS, $actions));
            if ($grant !== []) {
                $role->givePermissionTo($grant);
            }
        }

        if (Account::query()->exists()) {
            (new ChartOfAccountsSeeder)->run();
        }
        if (Employee::query()->exists()) {
            (new LeaveTypeSeeder)->run();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        User::query()->pluck('id')->each(fn (string $id) => Cache::forget(CacheKeys::permissions($id)));
    }

    public function down(): void
    {
        // Rights and accounts stay; removing them could break records or access granted on purpose.
    }
};
