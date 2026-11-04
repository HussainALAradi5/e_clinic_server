<?php

declare(strict_types=1);

use App\Enums\Payments\AppointmentFeeStatus;
use App\Enums\Payments\AppointmentFeeType;
use App\Models\Accounting\Account;
use App\Utilities\Access\RightsGranter;
use App\Utilities\Database\Constraints;
use Database\Seeders\FeeServicesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fees for visits that did not happen (no-show, late cancellation by the patient): one per appointment, charged
 * on the patient's invoice and waivable with a reason. Also adds the two fee items to the price list and the
 * `appointment_fees.*` rights on existing databases.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_fees', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->foreignUuid('appointment_id')->unique()->constrained('appointments')->restrictOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('status', 20);
            $table->foreignUuid('billable_service_id')->constrained('billable_services')->restrictOnDelete();
            $table->money('amount');
            $table->char('currency', 3);
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignUuid('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('waived_at')->nullable();
            $table->string('waiver_reason', 500)->nullable();
            $table->index(['patient_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
        Constraints::enum('appointment_fees', 'type', AppointmentFeeType::class);
        Constraints::enum('appointment_fees', 'status', AppointmentFeeStatus::class);
        Constraints::check('appointment_fees', 'chk_appointment_fees_amount', 'amount > 0');
        Constraints::check('appointment_fees', 'chk_appointment_fees_waiver', "status <> 'waived' OR (waived_by IS NOT NULL AND waiver_reason IS NOT NULL)");

        if (Account::query()->exists()) {
            app()->call([new FeeServicesSeeder, 'run']);
        }
        RightsGranter::grant('appointment_fees', ['read_own', 'read_all', 'create', 'write', 'delete', 'waive'], 'payments', 'appointment fees');
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_fees');
    }
};
