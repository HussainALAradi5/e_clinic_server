<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Inventory\Data;

use App\Modules\Pharmacy\Support\Qty;
use Brick\Math\BigDecimal;

/**
 * Stock-count correction of a lot: signed quantity (+ found, − missing / damaged) and why. Large corrections and
 * controlled drugs name the approving pharmacist.
 */
final readonly class StockAdjustmentData
{
    public function __construct(
        public BigDecimal $quantity,
        public string $reason,
        public ?string $approvedBy = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(Qty::of($input['quantity']), (string) $input['reason'], isset($input['approved_by']) ? (string) $input['approved_by'] : null);
    }
}
