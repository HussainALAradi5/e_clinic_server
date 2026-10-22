<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Dispensing\Data;

/**
 * Pharmacy hand-over: against a prescription, or over the counter (non-prescription medicines only).
 */
final readonly class DispenseData
{
    /**
     * @param  list<DispenseItemData>  $items
     */
    public function __construct(
        public string $patientId,
        public array $items,
        public ?string $prescriptionId = null,
        public ?string $witnessId = null,
        public ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        /** @var list<array<string, mixed>> $items */
        $items = $input['items'];

        return new self(
            (string) $input['patient_id'],
            array_map(DispenseItemData::fromArray(...), $items),
            isset($input['prescription_id']) ? (string) $input['prescription_id'] : null,
            isset($input['witness_id']) ? (string) $input['witness_id'] : null,
            isset($input['notes']) && $input['notes'] !== '' ? (string) $input['notes'] : null,
        );
    }
}
