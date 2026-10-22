<?php

use App\Enums\Pharmacy\DispensationKind;
use App\Enums\Pharmacy\MedicationRoute;
use App\Shared\Database\Constraints;
use App\Shared\Enums\GenericStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pharmacy › Dispensing: medicine leaving stock for a patient — handed over by the pharmacy (take_home) or given
     * during a session (administered). Lines record the batch (FEFO), the price and the cost snapshots.
     */
    public function up(): void
    {
        Schema::create('dispensations', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->string('kind', 20)->default(DispensationKind::TakeHome->value);
            $table->foreignUuid('prescription_id')->nullable()->index()->constrained('prescriptions')->nullOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->index()->constrained('appointments')->nullOnDelete();
            $table->foreignUuid('patient_id')->index()->constrained('patients')->restrictOnDelete();
            $table->foreignUuid('dispensed_by')->index()->constrained('employees')->restrictOnDelete();
            $table->foreignUuid('witness_id')->nullable()->index()->constrained('employees')->nullOnDelete();
            $table->string('status', 20)->default(GenericStatus::Completed->value);
            $table->timestampTz('dispensed_at');
            $table->foreignUuid('journal_entry_id')->nullable()->index()->constrained('journal_entries')->nullOnDelete();
            $table->foreignUuid('reversal_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->index(['status', 'dispensed_at']);
            $table->index('deleted_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Constraints::check('dispensations', 'chk_dispensations_witness', 'witness_id IS NULL OR witness_id <> dispensed_by');
        Constraints::check('dispensations', 'chk_dispensations_administered', "kind <> 'administered' OR appointment_id IS NOT NULL");
        Constraints::enum('dispensations', 'kind', DispensationKind::class);
        Constraints::enum('dispensations', 'status', GenericStatus::class);

        Schema::create('dispensation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dispensation_id')->index()->constrained('dispensations')->cascadeOnDelete();
            $table->foreignUuid('prescription_item_id')->nullable()->index()->constrained('prescription_items')->nullOnDelete();
            $table->foreignUuid('medication_id')->index()->constrained('medications')->restrictOnDelete();
            $table->foreignUuid('batch_id')->index()->constrained('medication_batches')->restrictOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->money('unit_price');
            $table->money('unit_cost');
            $table->string('route', 20)->nullable();
            $table->string('dose', 50)->nullable();
            $table->timestampsTz();
        });

        Constraints::check('dispensation_items', 'chk_dispensation_items_qty', 'quantity > 0');
        Constraints::enum('dispensation_items', 'route', MedicationRoute::class, nullable: true);
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensation_items');
        Schema::dropIfExists('dispensations');
    }
};
