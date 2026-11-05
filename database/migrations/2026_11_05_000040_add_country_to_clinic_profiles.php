<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The clinic's country (ISO 3166 code, picked from the country list); the existing clinic is in Bahrain. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_profiles', function (Blueprint $table): void {
            $table->char('country_code', 2)->nullable()->default('BH');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_profiles', function (Blueprint $table): void {
            $table->dropColumn('country_code');
        });
    }
};
