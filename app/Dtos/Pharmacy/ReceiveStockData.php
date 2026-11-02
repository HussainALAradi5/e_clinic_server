<?php

declare(strict_types=1);

namespace App\Dtos\Pharmacy;

use App\Utilities\Pharmacy\Qty;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * Goods receipt of one lot. `createExpense` books the purchase as an expense (submitted for approval) that, once
 * paid, moves its value from cash / bank into pharmacy inventory.
 */
final readonly class ReceiveStockData
{
    public function __construct(
        public string $medicationId,
        public string $batchNumber,
        public CarbonImmutable $expiryDate,
        public BigDecimal $quantity,
        public string $unitCost,
        public ?string $supplierId = null,
        public bool $createExpense = false,
        public ?string $vendorInvoiceNumber = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            (string) $input['medication_id'],
            trim((string) $input['batch_number']),
            CarbonImmutable::parse((string) $input['expiry_date'])->startOfDay(),
            Qty::of($input['quantity']),
            (string) $input['unit_cost'],
            isset($input['supplier_id']) ? (string) $input['supplier_id'] : null,
            (bool) ($input['create_expense'] ?? false),
            isset($input['vendor_invoice_number']) ? (string) $input['vendor_invoice_number'] : null,
        );
    }
}
