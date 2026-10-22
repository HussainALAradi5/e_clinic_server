<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

/**
 * Export / import rights of the Phase 2 lists (accounts, departments, employees, services, specialties, doctors,
 * rooms, patients, appointments) for databases that already ran 2026_10_16_000001: the same additive sync.
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
