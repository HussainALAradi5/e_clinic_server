<?php

use App\Enums\Shared\ApprovalStatus;
use App\Enums\Shared\PaymentMethod;
use App\Enums\Transactions\ExpenseCategory;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transactions › Operating expenses: draft → pending → approved by someone else → paid (posts Dr expense
     * + input VAT / Cr cash or bank). Vendor invoice scans are attachments.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->string('category', 30);
            $table->foreignUuid('expense_account_id')->index()->constrained('accounts')->restrictOnDelete();
            $table->foreignUuid('department_id')->nullable()->index()->constrained('departments')->nullOnDelete();
            $table->foreignUuid('payee_company_id')->nullable()->index()->constrained('companies')->nullOnDelete();
            $table->string('payee', 150)->nullable();
            $table->string('description', 500);
            $table->money('amount');
            $table->money('tax_amount')->default(0);
            $table->date('expense_date');
            $table->string('vendor_invoice_number', 100)->nullable();
            $table->string('status', 20)->default(ApprovalStatus::Draft->value);
            $table->foreignUuid('submitted_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->foreignUuid('cash_session_id')->nullable()->index()->constrained('cash_sessions')->nullOnDelete();
            $table->foreignUuid('journal_entry_id')->nullable()->unique()->constrained('journal_entries')->nullOnDelete();
            $table->index(['status', 'expense_date']);
            $table->index('deleted_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Constraints::check('expenses', 'chk_expenses_amount', 'amount > 0 AND tax_amount >= 0 AND tax_amount < amount');
        Constraints::check('expenses', 'chk_expenses_payee', 'payee_company_id IS NOT NULL OR payee IS NOT NULL');
        Constraints::check('expenses', 'chk_expenses_sod', 'approved_by IS NULL OR approved_by <> submitted_by');
        Constraints::check('expenses', 'chk_expenses_paid', "paid_at IS NULL OR (status = 'approved' AND payment_method IS NOT NULL)");
        Constraints::enum('expenses', 'category', ExpenseCategory::class);
        Constraints::enum('expenses', 'status', ApprovalStatus::class);
        Constraints::enum('expenses', 'payment_method', PaymentMethod::class, nullable: true);
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
