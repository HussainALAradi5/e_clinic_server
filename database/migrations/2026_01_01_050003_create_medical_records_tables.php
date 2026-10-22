<?php

use App\Shared\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Appointments › Medical records: SOAP notes and vitals of a visit (same id as the appointment), ICD-10 catalog,
     * diagnoses of a visit and append-only addenda after signing.
     */
    public function up(): void
    {
        Schema::create('icd10_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 10)->unique();
            $table->string('title', 255);
            $table->string('title_ar', 255)->nullable();
            $table->string('chapter', 10)->nullable();
            $table->boolean('is_billable')->default(true);
        });
        Constraints::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        Constraints::statement("CREATE INDEX icd10_codes_search_trgm ON icd10_codes USING gin ((code || ' ' || title || ' ' || coalesce(title_ar, '')) gin_trgm_ops)");

        Schema::create('medical_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreign('id')->references('id')->on('appointments')->restrictOnDelete();
            $table->text('chief_complaint')->nullable();
            $table->text('history')->nullable();
            $table->text('examination')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->decimal('temperature_c', 4, 1)->nullable();
            $table->unsignedSmallInteger('pulse_bpm')->nullable();
            $table->unsignedSmallInteger('systolic_bp')->nullable();
            $table->unsignedSmallInteger('diastolic_bp')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->unsignedSmallInteger('spo2_percent')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->foreignUuid('signed_by')->nullable()->index()->constrained('employees')->nullOnDelete();
            $table->timestampTz('signed_at')->nullable();
            $table->blameable();
            $table->version();
            $table->timestampsTz();
            $table->index('created_by');
            $table->index('updated_by');
        });
        Constraints::check('medical_records', 'chk_medical_records_vitals', '(spo2_percent IS NULL OR spo2_percent BETWEEN 0 AND 100) AND (pulse_bpm IS NULL OR pulse_bpm BETWEEN 20 AND 300)');

        Schema::create('record_diagnoses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->foreignUuid('icd10_code_id')->index()->constrained('icd10_codes')->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->string('notes', 255)->nullable();
            $table->unique(['medical_record_id', 'icd10_code_id'], 'record_diagnoses_unique');
        });
        Constraints::statement('CREATE UNIQUE INDEX record_diagnoses_one_primary ON record_diagnoses (medical_record_id) WHERE is_primary');

        Schema::create('record_addenda', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('medical_record_id')->index()->constrained('medical_records')->cascadeOnDelete();
            $table->text('text');
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });
        Constraints::immutable('record_addenda');
    }

    public function down(): void
    {
        Schema::dropIfExists('record_addenda');
        Schema::dropIfExists('record_diagnoses');
        Schema::dropIfExists('medical_records');
        Schema::dropIfExists('icd10_codes');
    }
};
