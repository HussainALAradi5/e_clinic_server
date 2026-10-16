<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identity › Roles & Permissions (spatie/laravel-permission tables with UUID keys).
     * The access matrix is stored as permissions `<resource>.<flag>` — no second table.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->genericEntity();
            $table->string('name', 100);
            $table->string('guard_name', 50)->default('sanctum');
            $table->string('display_name', 150);
            $table->string('display_name_ar', 150)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);

            $table->unique(['name', 'guard_name']);
            $table->index('deleted_by');
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('guard_name', 50)->default('sanctum');
            $table->string('module', 50);
            $table->string('description')->nullable();
            $table->timestampsTz();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->foreignUuid('permission_id')->index()->constrained('permissions')->cascadeOnDelete();
            $table->foreignUuid('role_id')->index()->constrained('roles')->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->foreignUuid('role_id')->index()->constrained('roles')->cascadeOnDelete();
            $table->string('model_type');
            $table->foreignUuid('model_id')->constrained('users')->cascadeOnDelete();

            $table->primary(['role_id', 'model_id', 'model_type']);
            $table->index(['model_id', 'model_type']);
        });

        // Direct user permissions are not used (rights come from roles only); the table keeps spatie's queries valid.
        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->foreignUuid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->string('model_type');
            $table->uuid('model_id');

            $table->primary(['permission_id', 'model_id', 'model_type']);
            $table->index(['model_id', 'model_type']);
        });

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
