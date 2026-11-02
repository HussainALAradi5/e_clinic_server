<?php

use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identity › List views: saved filters / sort / columns per user and resource.
     */
    public function up(): void
    {
        Schema::create('saved_views', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('resource', 50);
            $table->string('name', 100);
            $table->jsonb('query');
            $table->jsonb('columns')->default(new Expression("'[]'"));
            $table->boolean('is_shared')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestampsTz();

            $table->index(['user_id', 'resource'], 'saved_views_owner_resource_index');
            $table->unique(['user_id', 'resource', 'name'], 'saved_views_name_unique');
            $table->index(['resource', 'is_shared']);
        });

        Constraints::statement('CREATE UNIQUE INDEX saved_views_one_default ON saved_views (user_id, resource) WHERE is_default');
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_views');
    }
};
