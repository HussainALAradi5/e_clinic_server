<?php

declare(strict_types=1);

use App\Utilities\Access\RightsGranter;
use Illuminate\Database\Migrations\Migration;

/**
 * HR managers export every HR list; shifts were left out when they were added (config/access.php now gives
 * `shifts.export` to hr_manager).
 */
return new class extends Migration
{
    public function up(): void
    {
        RightsGranter::grant('shifts', ['export'], 'hr', 'shifts');
    }

    public function down(): void
    {
        // Rights stay: removing one could lock out a role an administrator changed by hand.
    }
};
