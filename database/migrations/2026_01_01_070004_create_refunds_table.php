<?php

use App\Enums\Shared\ApprovalStatus;
use App\Enums\Shared\PaymentMethod;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payments › Refunds against a payment: requested, approved by someone else, then paid out (processed_at).
     */
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->foreignUuid('payment_id')->index()->constrained('payments')->restrictOnDelete();
            $table->foreignUuid('invoice_id')->index()->constrained('invoices')->restrictOnDelete();
            $table->money('amount');
            $table->string('method', 20);
            $table->string('reason', 500);
            $table->string('status', 20)->default(ApprovalStatus::Pending->value);
            $table->foreignUuid('requested_by')->index()->constrained('users')->restrictOnDelete();
            $table->foreignUuid('approved_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignUuid('processed_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampTz('processed_at')->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->foreignUuid('cash_session_id')->nullable()->index()->constrained('cash_sessions')->nullOnDelete();
            $table->foreignUuid('journal_entry_id')->nullable()->unique()->constrained('journal_entries')->nullOnDelete();
            $table->index(['status', 'processed_at']);
            $table->index('deleted_by');
        });

        Constraints::check('refunds', 'chk_refunds_amount', 'amount > 0');
        Constraints::check('refunds', 'chk_refunds_sod', 'approved_by IS NULL OR approved_by <> requested_by');
        Constraints::check('refunds', 'chk_refunds_processed', "processed_at IS NULL OR status = 'approved'");
        Constraints::enum('refunds', 'method', PaymentMethod::class);
        Constraints::enum('refunds', 'status', ApprovalStatus::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
