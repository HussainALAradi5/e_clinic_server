<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gap-free counters for all numbered documents (DocumentNumberGenerator).
     */
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 30);
            $table->smallInteger('year');
            $table->string('prefix', 10);
            $table->unsignedInteger('last_number')->default(0);
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['document_type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
