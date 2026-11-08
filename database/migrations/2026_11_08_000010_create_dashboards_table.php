<?php

declare(strict_types=1);

use App\Models\Identity\Role;
use App\Utilities\Access\RightsGranter;
use Database\Seeders\DashboardSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saved dashboards (DSH-…) of any section. `section` has no CHECK constraint on purpose: adding a section is one enum
 * case and no schema change. The partial unique index is the one-default-per-section rule (a deleted dashboard does not
 * count); it works on both PostgreSQL and SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboards', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->string('section', 30)->index();
            $table->boolean('is_default')->default(false);
            $table->json('widgets');
        });
        DB::statement('CREATE UNIQUE INDEX dashboards_one_default_per_section ON dashboards (section) WHERE is_default AND NOT is_deleted');

        // Existing databases only: a fresh install gets both from its seeders (the deploy seeds only an empty database).
        if (Role::query()->where('guard_name', Config::string('access.guard'))->exists()) {
            RightsGranter::grant('dashboards', ['read_own', 'read_all', 'create', 'write', 'delete'], 'dashboards', 'dashboards');
            (new DashboardSeeder)->run();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboards');
    }
};
