<?php

declare(strict_types=1);

use App\Enums\Pharmacy\StockLocation;
use App\Enums\Pharmacy\StockMovementType;
use App\Shared\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock lives in the pharmacy or the treatment room. Every existing lot is in the pharmacy; the pharmacy moves units
 * to the treatment room (`transfer` movements), so the same batch can have a lot in each place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medication_batches', function (Blueprint $table) {
            $table->string('location', 20)->default(StockLocation::Pharmacy->value)->after('medication_id');
            $table->index(['medication_id', 'location', 'expiry_date']);
        });
        Constraints::enum('medication_batches', 'location', StockLocation::class);
        // One lot per batch number in each place (was: per medicine).
        Constraints::statement('DROP INDEX IF EXISTS medication_batches_lot_unique');
        Constraints::statement('CREATE UNIQUE INDEX medication_batches_lot_unique ON medication_batches (medication_id, batch_number, location) WHERE is_deleted = false');
        Constraints::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS chk_stock_movements_type');
        Constraints::enum('stock_movements', 'type', StockMovementType::class);
    }

    public function down(): void
    {
        Constraints::statement('DROP INDEX IF EXISTS medication_batches_lot_unique');
        Constraints::statement('CREATE UNIQUE INDEX medication_batches_lot_unique ON medication_batches (medication_id, batch_number) WHERE is_deleted = false');
        Constraints::statement('ALTER TABLE medication_batches DROP CONSTRAINT IF EXISTS chk_medication_batches_location');
        Schema::table('medication_batches', function (Blueprint $table) {
            $table->dropIndex(['medication_id', 'location', 'expiry_date']);
            $table->dropColumn('location');
        });
    }
};
