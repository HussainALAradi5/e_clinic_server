<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Medications\Data;

use Illuminate\Support\Arr;

/**
 * Medication input: catalogue columns, plus price and VAT rate stored on its price-list service.
 * On update only the provided keys change.
 */
final readonly class MedicationData
{
    public const FIELDS = [
        'code', 'generic_name', 'brand_name', 'name_ar', 'form', 'strength', 'unit', 'gtin', 'manufacturer_id',
        'origin_country_code', 'description', 'requires_prescription', 'is_controlled', 'reorder_level', 'storage_notes',
    ];

    /**
     * @param  array<string, mixed>  $values  medication columns
     */
    private function __construct(
        public array $values,
        public ?string $price,
        public ?string $taxRate,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        $values = array_map(fn (mixed $v): mixed => $v === '' ? null : $v, Arr::only($input, self::FIELDS));

        return new self(
            $values,
            isset($input['price']) ? (string) $input['price'] : null,
            isset($input['tax_rate']) ? (string) $input['tax_rate'] : null,
        );
    }
}
