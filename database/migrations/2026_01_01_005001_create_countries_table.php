<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reference › Countries: ISO-3166 list keyed by alpha-2 (seeded by CountrySeeder; never deleted).
     */
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->char('code', 2)->primary();
            $table->char('iso3', 3)->unique();
            $table->string('name', 100);
            $table->string('name_ar', 100);
            $table->string('nationality', 100);
            $table->string('nationality_ar', 100)->nullable();
            $table->string('phone_code', 6)->default('');
            $table->char('currency_code', 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(100);

            $table->index(['is_active', 'sort_order', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
