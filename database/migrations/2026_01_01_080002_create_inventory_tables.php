<?php

use App\Enums\Pharmacy\StockMovementType;
use App\Shared\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pharmacy › Inventory: stock lots (batch number, expiry, cost) and the append-only stock ledger —
     * a batch's qty_on_hand always equals the sum of its movements.
     */
    public function up(): void
    {
        Schema::create('medication_batches', function (Blueprint $table) {
            $table->genericEntity();
            $table->blameable();
            $table->foreignUuid('medication_id')->constrained('medications')->restrictOnDelete();
            $table->string('batch_number', 50);
            $table->date('expiry_date');
            $table->decimal('qty_received', 10, 2);
            $table->decimal('qty_on_hand', 10, 2);
            $table->money('unit_cost');
            $table->foreignUuid('supplier_id')->nullable()->index()->constrained('companies')->nullOnDelete();
            $table->timestampTz('received_at');
            $table->foreignUuid('expense_id')->nullable()->index()->constrained('expenses')->nullOnDelete();
            $table->boolean('is_quarantined')->default(false);
            $table->string('quarantine_reason', 255)->nullable();
            $table->index(['medication_id', 'expiry_date']);
            $table->index('deleted_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Constraints::statement('CREATE UNIQUE INDEX medication_batches_lot_unique ON medication_batches (medication_id, batch_number) WHERE is_deleted = false');
        Constraints::check('medication_batches', 'chk_medication_batches_qty', 'qty_on_hand >= 0 AND qty_on_hand <= qty_received');
        Constraints::check('medication_batches', 'chk_medication_batches_cost', 'unit_cost >= 0');

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('medication_id')->index()->constrained('medications')->restrictOnDelete();
            $table->foreignUuid('batch_id')->constrained('medication_batches')->restrictOnDelete();
            $table->string('type', 20);
            $table->decimal('quantity', 10, 2);
            $table->string('source_type', 50)->nullable();
            $table->uuid('source_id')->nullable();
            $table->string('reason', 255)->nullable();
            $table->foreignUuid('approved_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['batch_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });

        Constraints::check('stock_movements', 'chk_stock_movements_qty', 'quantity <> 0');
        Constraints::enum('stock_movements', 'type', StockMovementType::class);
        Constraints::immutable('stock_movements');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('medication_batches');
    }
};
