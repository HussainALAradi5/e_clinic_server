<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

/**
 * Adds `users.import` and `roles.import` to databases that already ran the export / import grant: the same additive
 * sync as 2026_10_16_000001 (idempotent — existing grants and access-matrix edits are kept).
 */
return new class extends Migration
{
    public function up(): void
    {
        /** @var Migration $grant */
        $grant = require __DIR__.'/2026_10_16_000001_grant_export_import_permissions.php';
        $grant->up();
    }

    public function down(): void
    {
        // Rights stay, as in 2026_10_16_000001.
    }
};
