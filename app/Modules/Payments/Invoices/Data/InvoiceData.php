<?php

declare(strict_types=1);

namespace App\Modules\Payments\Invoices\Data;

final readonly class InvoiceData
{
    /**
     * @param  list<InvoiceItemData>  $items
     */
    public function __construct(
        public string $patientId,
        public ?string $notes,
        public array $items,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?string $patientId = null): self
    {
        /** @var list<array<string, mixed>> $items */
        $items = $data['items'];

        return new self(
            $patientId ?? (string) $data['patient_id'],
            isset($data['notes']) ? (string) $data['notes'] : null,
            array_map(InvoiceItemData::fromArray(...), $items),
        );
    }
}
