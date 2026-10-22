<?php

use App\Enums\Appointments\AppointmentSource;
use App\Enums\Appointments\AppointmentStatus;
use App\Enums\Appointments\AppointmentType;
use App\Enums\Appointments\CancelledBy;
use App\Shared\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Appointments › Appointments: every time-slotted visit (consultations … treatment sessions). On PostgreSQL a
     * generated `time_range` with exclusion constraints is the last guard against provider / room double booking.
     * treatment_id gets its foreign key with the treatments table (Phase 3).
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->genericEntity();
            $table->blameable();
            $table->foreignUuid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignUuid('provider_id')->constrained('employees')->restrictOnDelete();
            $table->string('type', 20);
            $table->uuid('treatment_id')->nullable()->index();
            $table->unsignedSmallInteger('session_number')->nullable();
            $table->date('scheduled_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignUuid('room_id')->nullable()->index()->constrained('rooms')->nullOnDelete();
            $table->foreignUuid('billable_service_id')->nullable()->index()->constrained('billable_services')->restrictOnDelete();
            $table->string('status', 20);
            $table->boolean('is_attended')->default(false);
            $table->string('source', 20);
            $table->string('reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('rescheduled_from_id')->nullable()->unique()->constrained('appointments')->nullOnDelete();
            $table->string('cancelled_by', 20)->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('checked_in_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->version();
            $table->index(['provider_id', 'scheduled_date'], 'appointments_provider_date_index');
            $table->index(['patient_id', 'scheduled_date'], 'appointments_patient_date_index');
            $table->index(['status', 'scheduled_date'], 'appointments_status_date_index');
            $table->index('created_by');
        });

        Constraints::check('appointments', 'chk_appointments_time', 'end_time > start_time');
        Constraints::enum('appointments', 'type', AppointmentType::class);
        Constraints::enum('appointments', 'status', AppointmentStatus::class);
        Constraints::enum('appointments', 'source', AppointmentSource::class);
        Constraints::enum('appointments', 'cancelled_by', CancelledBy::class, nullable: true);

        $free = "status NOT IN ('cancelled', 'no_show', 'rescheduled') AND NOT is_deleted";
        Constraints::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        Constraints::statement('ALTER TABLE appointments ADD COLUMN time_range tsrange GENERATED ALWAYS AS (tsrange(scheduled_date + start_time, scheduled_date + end_time)) STORED');
        Constraints::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_no_overlap EXCLUDE USING gist (provider_id WITH =, time_range WITH &&) WHERE ({$free})");
        Constraints::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_room_no_overlap EXCLUDE USING gist (room_id WITH =, time_range WITH &&) WHERE (room_id IS NOT NULL AND {$free})");
        Constraints::statement("CREATE UNIQUE INDEX appointments_treatment_session_unique ON appointments (treatment_id, session_number) WHERE {$free}");
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
