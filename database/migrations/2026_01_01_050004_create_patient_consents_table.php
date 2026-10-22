<?php

use App\Enums\Appointments\ConsentType;
use App\Shared\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Appointments › Consents: signed patient consents (patient-wide, or for one visit, treatment plan or order).
     * Valid = not deleted, not revoked and not expired — computed, never stored. The signed form is an attachment.
     */
    public function up(): void
    {
        Schema::create('patient_consents', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->foreignUuid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->string('type', 30);
            $table->foreignUuid('appointment_id')->nullable()->index()->constrained('appointments')->nullOnDelete();
            $table->foreignUuid('treatment_id')->nullable()->index()->constrained('treatments')->nullOnDelete();
            $table->foreignUuid('clinical_order_id')->nullable()->index()->constrained('clinical_orders')->nullOnDelete();
            $table->text('description');
            $table->string('signed_by_name', 150);
            $table->string('signer_relationship', 30)->default('self');
            $table->foreignUuid('explained_by')->index()->constrained('employees')->restrictOnDelete();
            $table->foreignUuid('witness_id')->nullable()->index()->constrained('employees')->nullOnDelete();
            $table->timestampTz('signed_at');
            $table->date('valid_until')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->string('revocation_reason', 500)->nullable();
            $table->index(['patient_id', 'type']);
            $table->index('deleted_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Constraints::check('patient_consents', 'chk_patient_consents_revoked', '(revoked_at IS NULL) = (revocation_reason IS NULL)');
        Constraints::check('patient_consents', 'chk_patient_consents_witness', 'witness_id IS NULL OR witness_id <> explained_by');
        Constraints::enum('patient_consents', 'type', ConsentType::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_consents');
    }
};
