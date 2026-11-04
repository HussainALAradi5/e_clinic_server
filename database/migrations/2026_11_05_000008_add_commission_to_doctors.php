<?php

declare(strict_types=1);

use App\Enums\HR\CommissionType;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doctor earnings: each doctor's commission terms (none, % of their revenue, or a fixed amount per completed visit),
 * paid on the payslip as a Commission line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table): void {
            $table->string('commission_type', 20)->default(CommissionType::None->value);
            $table->decimal('commission_value', 10, 3)->default(0);
        });
        Constraints::enum('doctors', 'commission_type', CommissionType::class);
        Constraints::check('doctors', 'chk_doctors_commission_value', "commission_value >= 0 AND (commission_type <> 'percent' OR commission_value <= 100)");
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table): void {
            $table->dropColumn(['commission_type', 'commission_value']);
        });
    }
};
