<?php

declare(strict_types=1);

namespace App\Dtos\Payments;

use App\Enums\Payments\DiscountType;

/**
 * A line typed on the invoice screen: a price-list service (price from the list) or a free-text line with its own
 * price. `id` keeps an existing line (and its source link) when a draft is edited.
 */
final readonly class InvoiceItemData
{
    public function __construct(
        public ?string $id,
        public ?string $billableServiceId,
        public ?string $description,
        public string $quantity,
        public ?string $unitPrice,
        public ?DiscountType $discountType,
        public string $discountValue,
        public ?string $doctorId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public static function fromArray(array $item): self
    {
        return new self(
            isset($item['id']) ? (string) $item['id'] : null,
            isset($item['billable_service_id']) ? (string) $item['billable_service_id'] : null,
            isset($item['description']) ? (string) $item['description'] : null,
            (string) $item['quantity'],
            isset($item['unit_price']) ? (string) $item['unit_price'] : null,
            isset($item['discount_type']) ? DiscountType::from((string) $item['discount_type']) : null,
            (string) ($item['discount_value'] ?? '0'),
            isset($item['doctor_id']) ? (string) $item['doctor_id'] : null,
        );
    }
}
