<?php

use App\Enums\Appointments\ClinicalOrderType;
use App\Enums\Appointments\TreatmentPriority;
use App\Enums\Shared\GenericStatus;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Appointments › Clinical orders: imaging, lab tests, ECG and referrals (internal doctor / specialty or external
     * hospital). Images and result files are attachments of the order.
     */
    public function up(): void
    {
        Schema::create('clinical_orders', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->string('type', 20);
            $table->foreignUuid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignUuid('ordered_by')->index()->constrained('doctors')->restrictOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->index()->constrained('appointments')->nullOnDelete();
            $table->foreignUuid('treatment_id')->nullable()->index()->constrained('treatments')->nullOnDelete();
            $table->foreignUuid('billable_service_id')->nullable()->index()->constrained('billable_services')->nullOnDelete();
            $table->foreignUuid('referred_to_doctor_id')->nullable()->index()->constrained('doctors')->nullOnDelete();
            $table->foreignUuid('referred_to_specialty_id')->nullable()->index()->constrained('specialties')->nullOnDelete();
            $table->foreignUuid('referred_to_company_id')->nullable()->index()->constrained('companies')->nullOnDelete();
            $table->foreignUuid('referral_appointment_id')->nullable()->index()->constrained('appointments')->nullOnDelete();
            $table->string('body_part', 100)->nullable();
            $table->text('clinical_notes')->nullable();
            $table->string('priority', 10)->default(TreatmentPriority::Medium->value);
            $table->string('status', 20)->default(GenericStatus::Pending->value);
            $table->foreignUuid('room_id')->nullable()->index()->constrained('rooms')->nullOnDelete();
            $table->foreignUuid('performed_by')->nullable()->index()->constrained('employees')->nullOnDelete();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('report')->nullable();
            $table->foreignUuid('reported_by')->nullable()->index()->constrained('doctors')->nullOnDelete();
            $table->boolean('is_abnormal')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->version();
            $table->index(['patient_id', 'type']);
            $table->index(['status', 'priority']);
            $table->index('deleted_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Constraints::check('clinical_orders', 'chk_clinical_orders_billing', "type = 'referral' OR billable_service_id IS NOT NULL");
        Constraints::check('clinical_orders', 'chk_clinical_orders_referral', "type <> 'referral' OR num_nonnulls(referred_to_doctor_id, referred_to_specialty_id, referred_to_company_id) >= 1");
        Constraints::check('clinical_orders', 'chk_clinical_orders_referral_cols', "type = 'referral' OR num_nonnulls(referred_to_doctor_id, referred_to_specialty_id, referred_to_company_id, referral_appointment_id) = 0");
        Constraints::enum('clinical_orders', 'type', ClinicalOrderType::class);
        Constraints::enum('clinical_orders', 'priority', TreatmentPriority::class);
        Constraints::enum('clinical_orders', 'status', GenericStatus::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_orders');
    }
};
