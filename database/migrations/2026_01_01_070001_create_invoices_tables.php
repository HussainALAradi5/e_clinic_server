<?php

use App\Enums\Payments\DiscountType;
use App\Enums\Payments\InvoiceStatus;
use App\Shared\Database\Constraints;
use App\Shared\Enums\Currency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payments › Invoices: one open draft per patient collects every charge (visits, sessions, orders, medicines);
     * issuing numbers it and posts revenue. Invoice lines are the only link between clinical records and billing.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->genericEntity();
            $table->blameable();
            $table->string('invoice_number', 20)->nullable()->unique();
            $table->foreignUuid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->date('issue_date')->nullable()->index();
            $table->date('due_date')->nullable();
            $table->char('currency', 3)->default('BHD');
            $table->money('subtotal')->default(0);
            $table->money('discount_total')->default(0);
            $table->money('tax_total')->default(0);
            $table->money('total')->default(0);
            $table->money('paid_amount')->default(0);
            $table->money('refunded_amount')->default(0);
            $table->money('balance_due')->default(0);
            $table->string('status', 20)->default(InvoiceStatus::Draft->value);
            $table->text('notes')->nullable();
            $table->foreignUuid('issued_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->foreignUuid('journal_entry_id')->nullable()->index()->constrained('journal_entries')->nullOnDelete();
            $table->foreignUuid('reversal_journal_entry_id')->nullable()->index()->constrained('journal_entries')->nullOnDelete();
            $table->version();
            $table->index(['patient_id', 'status'], 'invoices_patient_status_index');
            $table->index('deleted_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->index()->constrained('invoices')->cascadeOnDelete();
            $table->foreignUuid('billable_service_id')->nullable()->index()->constrained('billable_services')->nullOnDelete();
            $table->string('chargeable_type', 50)->nullable();
            $table->uuid('chargeable_id')->nullable();
            $table->foreignUuid('doctor_id')->nullable()->index()->constrained('doctors')->nullOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 8, 2)->default(1);
            $table->money('unit_price');
            $table->string('discount_type', 20)->nullable();
            $table->money('discount_value')->default(0);
            $table->money('discount_amount')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->money('tax_amount')->default(0);
            $table->money('line_total');
            $table->foreignUuid('revenue_account_id')->index()->constrained('accounts')->restrictOnDelete();
            $table->timestampsTz();
            $table->index(['chargeable_type', 'chargeable_id'], 'invoice_items_chargeable_index');
        });

        Constraints::check('invoices', 'chk_invoices_amounts', 'total >= 0 AND paid_amount >= 0 AND refunded_amount >= 0 AND balance_due >= 0');
        Constraints::check('invoices', 'chk_invoices_total', 'total = subtotal - discount_total + tax_total');
        Constraints::enum('invoices', 'currency', Currency::class);
        Constraints::enum('invoices', 'status', InvoiceStatus::class);
        Constraints::statement("CREATE UNIQUE INDEX invoices_one_open_draft ON invoices (patient_id) WHERE status = 'draft' AND NOT is_deleted");
        Constraints::check('invoice_items', 'chk_invoice_items_qty', 'quantity > 0');
        Constraints::check('invoice_items', 'chk_invoice_items_discount', 'discount_amount >= 0 AND discount_amount <= quantity * unit_price');
        Constraints::enum('invoice_items', 'discount_type', DiscountType::class, nullable: true);
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
