<?php

use App\Enums\Appointments\BloodGroup;
use App\Shared\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Appointments › Patients: the medical extension of a person (same id). Personal data stays in persons.
     */
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreign('id')->references('id')->on('persons')->restrictOnDelete();
            $table->string('blood_group', 10)->default(BloodGroup::UNKNOWN->value);
            $table->text('allergies')->nullable();
            $table->text('chronic_conditions')->nullable();
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relation', 50)->nullable();
            $table->foreignUuid('insurance_company_id')->nullable()->index()->constrained('companies')->nullOnDelete();
            $table->string('insurance_policy_number', 100)->nullable();
            $table->date('insurance_expiry_date')->nullable();
            $table->decimal('insurance_coverage_percent', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->version();
            $table->timestampsTz();
        });

        Constraints::enum('patients', 'blood_group', BloodGroup::class);
        Constraints::check('patients', 'chk_patients_coverage', 'insurance_coverage_percent IS NULL OR insurance_coverage_percent BETWEEN 0 AND 100');
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
