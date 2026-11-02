<?php

declare(strict_types=1);

namespace App\Dtos\Pharmacy;

use App\Enums\Pharmacy\MedicationRoute;
use App\Utilities\Pharmacy\Qty;

/**
 * One prescribed medicine with its structured dosage.
 */
final readonly class PrescriptionItemData
{
    public function __construct(
        public string $medicationId,
        public string $dose,
        public string $frequency,
        public MedicationRoute $route,
        public int $durationDays,
        public string $quantity,
        public ?string $instructions = null,
    ) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public static function fromArray(array $item): self
    {
        return new self(
            (string) $item['medication_id'],
            (string) $item['dose'],
            (string) $item['frequency'],
            MedicationRoute::from((string) $item['route']),
            (int) $item['duration_days'],
            Qty::str($item['quantity']),
            isset($item['instructions']) && $item['instructions'] !== '' ? (string) $item['instructions'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'medication_id' => $this->medicationId,
            'dose' => $this->dose,
            'frequency' => $this->frequency,
            'route' => $this->route,
            'duration_days' => $this->durationDays,
            'quantity' => $this->quantity,
            'instructions' => $this->instructions,
        ];
    }
}
