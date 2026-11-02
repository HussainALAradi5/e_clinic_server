<?php

use App\Enums\Reference\CompanyType;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reference › Companies: manufacturers, suppliers, insurers, external labs and hospitals in one table.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->genericEntity();
            $table->blameable();
            $table->string('type', 20);
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->text('description')->nullable();
            $table->char('country_code', 2)->index();
            $table->foreignUuid('address_id')->nullable()->index()->constrained('addresses')->nullOnDelete();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('contact_person', 150)->nullable();
            $table->string('vat_number', 30)->nullable();
            $table->string('cr_number', 30)->nullable();

            $table->foreign('country_code')->references('code')->on('countries')->restrictOnDelete();
            $table->index(['type', 'name'], 'companies_type_name_index');
            $table->index('created_by');
            $table->index('updated_by');
            $table->index('deleted_by');
        });

        Constraints::enum('companies', 'type', CompanyType::class);
        Constraints::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        Constraints::statement('CREATE INDEX companies_name_trgm ON companies USING gin (name gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
