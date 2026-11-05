<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online payments become a clinic setting: off until an admin switches them on, with the Tap API address, payment
 * sources and secret key (stored encrypted) kept with the clinic profile instead of only in `.env`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_profiles', function (Blueprint $table): void {
            $table->boolean('online_payments_enabled')->default(false);
            $table->string('tap_base_url', 255)->nullable();
            $table->string('tap_source', 60)->nullable();
            $table->text('tap_secret_key')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clinic_profiles', function (Blueprint $table): void {
            $table->dropColumn(['online_payments_enabled', 'tap_base_url', 'tap_source', 'tap_secret_key']);
        });
    }
};
