<?php

declare(strict_types=1);

use App\Enums\Payments\OnlinePaymentStatus;
use App\Enums\Payments\WalletEntryType;
use App\Enums\Shared\PaymentMethod;
use App\Models\Accounting\Account;
use App\Utilities\Access\RightsGranter;
use App\Utilities\Database\Constraints;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online payments (OPY): one row per attempt to pay an invoice through the gateway. Also adds the `online` payment
 * method (and its constraints), the 1140 Online payments clearing account and the `online_payments.*` rights.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_payments', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->foreignUuid('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->money('amount');
            $table->char('currency', 3);
            $table->string('status', 20);
            $table->string('provider', 20);
            $table->string('charge_id', 100)->nullable()->unique();
            $table->string('checkout_url', 1000)->nullable();
            $table->string('gateway_status', 30)->nullable();
            $table->string('failure_reason', 300)->nullable();
            $table->foreignUuid('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->foreignUuid('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('captured_at')->nullable();
            $table->index(['status', 'expires_at']);
            $table->index(['patient_id', 'created_at']);
        });
        Constraints::enum('online_payments', 'status', OnlinePaymentStatus::class);
        Constraints::check('online_payments', 'chk_online_payments_amount', 'amount > 0');

        foreach (['payments' => 'method', 'refunds' => 'method'] as $table => $column) {
            Constraints::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS chk_{$table}_{$column}");
            Constraints::enum($table, $column, PaymentMethod::class);
        }
        Constraints::statement('ALTER TABLE expenses DROP CONSTRAINT IF EXISTS chk_expenses_payment_method');
        Constraints::enum('expenses', 'payment_method', PaymentMethod::class, nullable: true);
        Constraints::statement('ALTER TABLE patient_wallet_entries DROP CONSTRAINT IF EXISTS chk_patient_wallet_entries_method');
        Constraints::enum('patient_wallet_entries', 'method', PaymentMethod::class, nullable: true);
        Constraints::statement('ALTER TABLE patient_wallet_entries DROP CONSTRAINT IF EXISTS chk_patient_wallet_entries_type');
        Constraints::enum('patient_wallet_entries', 'type', WalletEntryType::class);

        if (Account::query()->exists()) {
            (new ChartOfAccountsSeeder)->run();
        }
        RightsGranter::grant('online_payments', ['read_own', 'read_all', 'create', 'write', 'delete'], 'payments', 'online payments');
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payments');
    }
};
