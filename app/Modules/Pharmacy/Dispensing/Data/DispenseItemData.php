<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Dispensing\Data;

use App\Enums\Pharmacy\MedicationRoute;
use App\Modules\Pharmacy\Support\Qty;
use Brick\Math\BigDecimal;

/**
 * One medicine to hand over or give: quantity, the prescription line it fills, an optional scanned lot (else FEFO),
 * and for medicines given in a session the route and dose.
 */
final readonly class DispenseItemData
{
    public function __construct(
        public string $medicationId,
        public BigDecimal $quantity,
        public ?string $prescriptionItemId = null,
        public ?string $batchId = null,
        public ?MedicationRoute $route = null,
        public ?string $dose = null,
    ) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public static function fromArray(array $item): self
    {
        $text = fn (string $key): ?string => isset($item[$key]) && $item[$key] !== '' ? (string) $item[$key] : null;

        return new self(
            (string) $item['medication_id'],
            Qty::of($item['quantity']),
            $text('prescription_item_id'),
            $text('batch_id'),
            isset($item['route']) ? MedicationRoute::from((string) $item['route']) : null,
            $text('dose'),
        );
    }
}
