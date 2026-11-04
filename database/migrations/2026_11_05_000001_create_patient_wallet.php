<?php

declare(strict_types=1);

use App\Enums\Payments\WalletEntryType;
use App\Enums\Shared\PaymentMethod;
use App\Models\Accounting\Account;
use App\Models\Identity\Permission;
use App\Models\Identity\Role;
use App\Utilities\Database\Constraints;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Patient balance (wallet): money the clinic holds for a patient.
 * - patients.wallet_balance: the running balance (never negative), changed only by WalletService under a row lock.
 * - patient_wallet_entries (WLT-2026-000001): every movement — deposit, invoice paid from the balance, payment
 *   returned, paid back, adjustment, fee — with the balance after it and its journal entry.
 * - payment method `patient_balance`; accounts 2500 Patient deposits and 4600 Fees revenue; rights `wallet.*`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->money('wallet_balance')->default(0);
        });
        Constraints::check('patients', 'chk_patients_wallet_balance', 'wallet_balance >= 0');

        Schema::create('patient_wallet_entries', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->foreignUuid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->string('type', 30);
            $table->money('amount');
            $table->money('balance_after');
            $table->char('currency', 3);
            $table->string('method', 20)->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->foreignUuid('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignUuid('cash_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->string('reason', 500)->nullable();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->index(['patient_id', 'created_at']);
        });
        Constraints::enum('patient_wallet_entries', 'type', WalletEntryType::class);
        Constraints::enum('patient_wallet_entries', 'method', PaymentMethod::class, nullable: true);
        Constraints::check('patient_wallet_entries', 'chk_patient_wallet_entries_amount', 'amount > 0 AND balance_after >= 0');

        foreach (['payments' => 'method', 'refunds' => 'method'] as $table => $column) {
            Constraints::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS chk_{$table}_{$column}");
            Constraints::enum($table, $column, PaymentMethod::class);
        }
        Constraints::statement('ALTER TABLE expenses DROP CONSTRAINT IF EXISTS chk_expenses_payment_method');
        Constraints::enum('expenses', 'payment_method', PaymentMethod::class, nullable: true);

        if (Account::query()->exists()) {
            (new ChartOfAccountsSeeder)->run();
        }
        $this->grantRights();
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_wallet_entries');
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('wallet_balance');
        });
    }

    /** Existing databases: `wallet.*` to the roles that have them in config/access.php. */
    private function grantRights(): void
    {
        $guard = Config::string('access.guard');
        if (Role::query()->where('guard_name', $guard)->doesntExist()) {
            return;
        }
        Model::preventLazyLoading(false);
        $flags = ['read_own', 'read_all', 'create', 'write', 'delete', 'deposit', 'withdraw', 'adjust'];
        foreach ($flags as $flag) {
            Permission::query()->firstOrCreate(['name' => "wallet.{$flag}", 'guard_name' => $guard], ['module' => 'payments', 'description' => ucfirst(str_replace('_', ' ', $flag)).' — patient balance']);
        }
        /** @var array<string, array{access: string|array<string, list<string>>, actions?: string|list<string>}> $roles */
        $roles = Config::array('access.roles');
        foreach (Role::query()->where('guard_name', $guard)->whereIn('name', array_keys($roles))->get() as $role) {
            $config = $roles[$role->name];
            if ($config['access'] === '*') {
                $role->givePermissionTo(array_map(fn (string $f): string => "wallet.{$f}", $flags));

                continue;
            }
            $grant = array_map(fn (string $f): string => "wallet.{$f}", $config['access']['wallet'] ?? []);
            $actions = is_array($config['actions'] ?? null) ? $config['actions'] : [];
            $grant = [...$grant, ...array_values(array_filter($actions, fn (string $a): bool => str_starts_with($a, 'wallet.')))];
            if ($grant !== []) {
                $role->givePermissionTo($grant);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
