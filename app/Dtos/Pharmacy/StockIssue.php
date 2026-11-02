<?php

declare(strict_types=1);

namespace App\Dtos\Pharmacy;

use App\Models\Pharmacy\MedicationBatch;
use Brick\Math\BigDecimal;

/**
 * One batch's share of an issue (FEFO allocation).
 */
final readonly class StockIssue
{
    public function __construct(public MedicationBatch $batch, public BigDecimal $quantity) {}
}
