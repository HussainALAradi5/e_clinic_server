<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Appointments › Doctors: medical specialties (General Practice, Pediatrics …).
     */
    public function up(): void
    {
        Schema::create('specialties', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->text('description')->nullable();
            $table->index('deleted_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialties');
    }
};
