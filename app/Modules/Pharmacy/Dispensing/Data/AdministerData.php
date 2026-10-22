<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Dispensing\Data;

/**
 * Medicines given during a session (injection, infusion, dressing): no prescription, route and dose per item.
 */
final readonly class AdministerData
{
    /**
     * @param  list<DispenseItemData>  $items
     */
    public function __construct(
        public array $items,
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
            array_map(DispenseItemData::fromArray(...), $items),
            isset($input['witness_id']) ? (string) $input['witness_id'] : null,
            isset($input['notes']) && $input['notes'] !== '' ? (string) $input['notes'] : null,
        );
    }
}
