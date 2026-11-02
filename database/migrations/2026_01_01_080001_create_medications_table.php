<?php

use App\Enums\Pharmacy\DispenseUnit;
use App\Enums\Pharmacy\MedicationForm;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pharmacy › Medications: the catalogue. Price, VAT and revenue account live on the linked price-list service
     * (one price list); stock on hand is the sum of the batches.
     */
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table) {
            $table->genericEntity();
            $table->blameable();
            $table->string('generic_name', 150);
            $table->string('brand_name', 150)->nullable();
            $table->string('name_ar', 150)->nullable();
            $table->string('form', 20);
            $table->string('strength', 50);
            $table->string('unit', 20);
            $table->string('gtin', 14)->nullable()->unique();
            $table->foreignUuid('manufacturer_id')->nullable()->index()->constrained('companies')->nullOnDelete();
            $table->char('origin_country_code', 2)->nullable()->index();
            $table->text('description')->nullable();
            $table->foreignUuid('billable_service_id')->unique()->constrained('billable_services')->restrictOnDelete();
            $table->boolean('requires_prescription')->default(true);
            $table->boolean('is_controlled')->default(false);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->string('storage_notes', 255)->nullable();
            $table->foreign('origin_country_code')->references('code')->on('countries')->nullOnDelete();
            $table->index('generic_name');
            $table->index('deleted_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Constraints::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        Constraints::statement('CREATE INDEX medications_search_trgm ON medications USING gin (generic_name gin_trgm_ops, brand_name gin_trgm_ops)');
        Constraints::check('medications', 'chk_medications_reorder', 'reorder_level >= 0');
        Constraints::enum('medications', 'form', MedicationForm::class);
        Constraints::enum('medications', 'unit', DispenseUnit::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('medications');
    }
};
