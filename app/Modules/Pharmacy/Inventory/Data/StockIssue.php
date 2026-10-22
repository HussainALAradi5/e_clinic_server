<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Inventory\Data;

use App\Modules\Pharmacy\Inventory\Models\MedicationBatch;
use Brick\Math\BigDecimal;

/**
 * One batch's share of an issue (FEFO allocation).
 */
final readonly class StockIssue
{
    public function __construct(public MedicationBatch $batch, public BigDecimal $quantity) {}
}
