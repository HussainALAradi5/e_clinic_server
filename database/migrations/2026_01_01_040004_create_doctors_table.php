<?php

use App\Shared\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Appointments › Doctors: clinical profile — subtype of employees (same id as employees, users and persons).
     */
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreign('id')->references('id')->on('employees')->restrictOnDelete();
            $table->foreignUuid('specialty_id')->index()->constrained('specialties')->restrictOnDelete();
            $table->string('license_number', 50)->unique();
            $table->date('license_expiry_date');
            $table->string('title', 50)->nullable();
            $table->foreignUuid('consultation_service_id')->index()->constrained('billable_services')->restrictOnDelete();
            $table->foreignUuid('follow_up_service_id')->index()->constrained('billable_services')->restrictOnDelete();
            $table->unsignedSmallInteger('default_slot_minutes')->default(15);
            $table->unsignedSmallInteger('max_daily_appointments')->nullable();
            $table->boolean('is_accepting_patients')->default(true);
            $table->foreignUuid('default_room_id')->nullable()->index()->constrained('rooms')->nullOnDelete();
            $table->text('bio')->nullable();
            $table->timestampsTz();
        });

        Constraints::check('doctors', 'chk_doctors_slot', 'default_slot_minutes BETWEEN 5 AND 240');
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
