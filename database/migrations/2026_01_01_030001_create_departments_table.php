<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR › Departments: the department tree (manager_id is added with the employees table).
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->foreignUuid('parent_id')->nullable()->index()->constrained('departments')->nullOnDelete();
            $table->string('cost_center_code', 20)->nullable();
            $table->index('deleted_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
