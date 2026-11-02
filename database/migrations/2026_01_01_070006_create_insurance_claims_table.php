<?php

use App\Enums\Shared\ApprovalStatus;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payments › Insurance claims of the insurer's share of an issued invoice. Approval records a normal payment
     * (method insurance); the insurer's bank remittance clears the insurance receivable.
     */
    public function up(): void
    {
        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->foreignUuid('invoice_id')->index()->constrained('invoices')->restrictOnDelete();
            $table->foreignUuid('patient_id')->index()->constrained('patients')->restrictOnDelete();
            $table->foreignUuid('insurer_id')->constrained('companies')->restrictOnDelete();
            $table->string('policy_number', 100);
            $table->decimal('coverage_percent', 5, 2);
            $table->string('pre_authorization_number', 50)->nullable();
            $table->money('claimed_amount');
            $table->money('approved_amount')->nullable();
            $table->string('status', 20)->default(ApprovalStatus::Draft->value);
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->string('insurer_reference', 100)->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->foreignUuid('payment_id')->nullable()->unique()->constrained('payments')->nullOnDelete();
            $table->date('remitted_at')->nullable();
            $table->string('remittance_reference', 100)->nullable();
            $table->foreignUuid('remittance_journal_entry_id')->nullable()->index()->constrained('journal_entries')->nullOnDelete();
            $table->index(['insurer_id', 'status'], 'insurance_claims_insurer_status_index');
            $table->index('deleted_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Constraints::check('insurance_claims', 'chk_insurance_claims_amounts', 'claimed_amount > 0 AND (approved_amount IS NULL OR (approved_amount >= 0 AND approved_amount <= claimed_amount))');
        Constraints::check('insurance_claims', 'chk_insurance_claims_remittance', "remitted_at IS NULL OR (status = 'approved' AND payment_id IS NOT NULL)");
        Constraints::enum('insurance_claims', 'status', ApprovalStatus::class);
        Constraints::statement("CREATE UNIQUE INDEX insurance_claims_one_open ON insurance_claims (invoice_id) WHERE status IN ('draft', 'pending', 'approved') AND NOT is_deleted");
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claims');
    }
};
