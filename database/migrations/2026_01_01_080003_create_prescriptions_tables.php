<?php

use App\Enums\Pharmacy\MedicationRoute;
use App\Shared\Database\Constraints;
use App\Shared\Enums\GenericStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pharmacy › Prescriptions written by a doctor: pending → in_progress (partly dispensed) → completed / cancelled.
     * Dispensed quantities are computed from the dispensations, never stored.
     */
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->foreignUuid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignUuid('doctor_id')->index()->constrained('doctors')->restrictOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->index()->constrained('appointments')->nullOnDelete();
            $table->foreignUuid('treatment_id')->nullable()->index()->constrained('treatments')->nullOnDelete();
            $table->string('status', 20)->default(GenericStatus::Pending->value);
            $table->date('valid_until');
            $table->text('notes')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->index(['patient_id', 'status']);
            $table->index(['status', 'valid_until']);
            $table->index('deleted_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Constraints::enum('prescriptions', 'status', GenericStatus::class);

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prescription_id')->index()->constrained('prescriptions')->cascadeOnDelete();
            $table->foreignUuid('medication_id')->index()->constrained('medications')->restrictOnDelete();
            $table->string('dose', 50);
            $table->string('frequency', 50);
            $table->string('route', 20);
            $table->unsignedSmallInteger('duration_days');
            $table->decimal('quantity', 10, 2);
            $table->string('instructions', 255)->nullable();
            $table->timestampsTz();
        });

        Constraints::check('prescription_items', 'chk_prescription_items_qty', 'quantity > 0');
        Constraints::check('prescription_items', 'chk_prescription_items_duration', 'duration_days > 0');
        Constraints::enum('prescription_items', 'route', MedicationRoute::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
    }
};
