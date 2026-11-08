<?php

declare(strict_types=1);

use App\Enums\Identity\AuditAction;
use App\Models\Identity\Role;
use App\Utilities\Access\RightsGranter;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Config;

/**
 * Allows the `viewed` audit action. Existing databases only (a fresh install gets this from its seeder): the patient file
 * event log right for admins and reception.
 */
return new class extends Migration
{
    public function up(): void
    {
        Constraints::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS chk_audit_logs_action');
        Constraints::enum('audit_logs', 'action', AuditAction::class);

        if (Role::query()->where('guard_name', Config::string('access.guard'))->exists()) {
            RightsGranter::grant('activity', ['read_all'], 'identity', 'event log');
        }
    }

    public function down(): void
    {
        // Rights stay: removing one here could take it from a role an administrator changed by hand.
    }
};
