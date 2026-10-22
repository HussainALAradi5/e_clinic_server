<?php

use App\Enums\Payments\PaymentStatus;
use App\Shared\Database\Constraints;
use App\Shared\Enums\Currency;
use App\Shared\Enums\PaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payments › Money received against an invoice (one row per method; split payments are several rows).
     * Never edited: same-day void or refund.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->foreignUuid('invoice_id')->index()->constrained('invoices')->restrictOnDelete();
            $table->foreignUuid('patient_id')->index()->constrained('patients')->restrictOnDelete();
            $table->money('amount');
            $table->char('currency', 3)->default('BHD');
            $table->string('method', 20);
            $table->string('status', 20)->default(PaymentStatus::Completed->value);
            $table->string('reference_number', 100)->nullable();
            $table->char('card_last4', 4)->nullable();
            $table->timestampTz('paid_at');
            $table->foreignUuid('received_by')->index()->constrained('users')->restrictOnDelete();
            $table->foreignUuid('cash_session_id')->nullable()->index()->constrained('cash_sessions')->nullOnDelete();
            $table->foreignUuid('journal_entry_id')->nullable()->unique()->constrained('journal_entries')->nullOnDelete();
            $table->money('refunded_amount')->default(0);
            $table->uuid('idempotency_key')->unique();
            $table->timestampTz('voided_at')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->string('notes', 500)->nullable();
            $table->index(['paid_at', 'method'], 'payments_paid_at_method_index');
            $table->index('deleted_by');
        });

        Constraints::check('payments', 'chk_payments_amount', 'amount > 0');
        Constraints::check('payments', 'chk_payments_refunded', 'refunded_amount >= 0 AND refunded_amount <= amount');
        Constraints::check('payments', 'chk_payments_cash_session', "method <> 'cash' OR cash_session_id IS NOT NULL");
        Constraints::enum('payments', 'currency', Currency::class);
        Constraints::enum('payments', 'method', PaymentMethod::class);
        Constraints::enum('payments', 'status', PaymentStatus::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
