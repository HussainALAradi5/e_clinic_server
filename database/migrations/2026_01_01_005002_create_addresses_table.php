<?php

use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reference › Addresses: one address model owned by a person / company through its `address_id`.
     */
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('country_code', 2)->index();
            $table->string('flat', 20)->nullable();
            $table->string('building', 20)->nullable();
            $table->string('road', 20)->nullable();
            $table->string('block', 10)->nullable();
            $table->string('area', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('governorate', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('line1')->nullable();
            $table->string('line2')->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->timestampsTz();
            $table->blameable();

            $table->foreign('country_code')->references('code')->on('countries')->restrictOnDelete();
            $table->index('created_by');
            $table->index('updated_by');
        });

        Constraints::check('addresses', 'chk_addresses_bahrain', "country_code <> 'BH' OR (building IS NOT NULL AND road IS NOT NULL AND block IS NOT NULL)");
        Constraints::check('addresses', 'chk_addresses_coords', '(latitude IS NULL) = (longitude IS NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
