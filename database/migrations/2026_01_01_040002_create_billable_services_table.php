<?php

use App\Enums\Appointments\ConsentType;
use App\Enums\Payments\ServiceCategory;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payments › Service catalog: the single price list (consultations, procedures, lab, radiology …).
     */
    public function up(): void
    {
        Schema::create('billable_services', function (Blueprint $table) {
            $table->genericEntity();
            $table->blameable();
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->string('category', 20)->index();
            $table->money('price');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->foreignUuid('revenue_account_id')->index()->constrained('accounts')->restrictOnDelete();
            $table->foreignUuid('specialty_id')->nullable()->index()->constrained('specialties')->nullOnDelete();
            $table->boolean('is_insurable')->default(true);
            $table->string('consent_type', 30)->nullable();
            $table->index('deleted_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Constraints::check('billable_services', 'chk_billable_services_price', 'price >= 0');
        Constraints::check('billable_services', 'chk_billable_services_tax', 'tax_rate BETWEEN 0 AND 100');
        Constraints::enum('billable_services', 'category', ServiceCategory::class);
        Constraints::enum('billable_services', 'consent_type', ConsentType::class, nullable: true);
    }

    public function down(): void
    {
        Schema::dropIfExists('billable_services');
    }
};
