<?php

use App\Enums\Appointments\TreatmentPriority;
use App\Enums\Appointments\TreatmentType;
use App\Shared\Database\Constraints;
use App\Shared\Enums\GenericStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Appointments › Treatments: treatment plans; their sessions are appointments (type treatment_session).
     * Attendance and progress are computed from those sessions, never stored.
     */
    public function up(): void
    {
        Schema::create('treatments', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->string('type', 30);
            $table->foreignUuid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignUuid('doctor_id')->index()->constrained('doctors')->restrictOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->index()->constrained('appointments')->nullOnDelete();
            $table->foreignUuid('billable_service_id')->nullable()->index()->constrained('billable_services')->nullOnDelete();
            $table->string('title', 150);
            $table->text('diagnosis')->nullable();
            $table->text('plan')->nullable();
            $table->string('priority', 10)->default(TreatmentPriority::Medium->value);
            $table->string('status', 20)->default(GenericStatus::Pending->value);
            $table->unsignedSmallInteger('planned_sessions')->default(1);
            $table->unsignedSmallInteger('session_duration_minutes')->default(30);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->version();
            $table->index(['patient_id', 'status']);
            $table->index(['priority', 'status']);
            $table->index('deleted_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Constraints::check('treatments', 'chk_treatments_sessions', 'planned_sessions >= 1');
        Constraints::check('treatments', 'chk_treatments_session_duration', 'session_duration_minutes BETWEEN 5 AND 480');
        Constraints::enum('treatments', 'type', TreatmentType::class);
        Constraints::enum('treatments', 'priority', TreatmentPriority::class);
        Constraints::enum('treatments', 'status', GenericStatus::class);
        // Sessions point at their plan (the column was created with the appointments table in Phase 2).
        Constraints::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_treatment_id_foreign FOREIGN KEY (treatment_id) REFERENCES treatments (id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        Constraints::statement('ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_treatment_id_foreign');
        Schema::dropIfExists('treatments');
    }
};
