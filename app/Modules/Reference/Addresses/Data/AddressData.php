<?php

declare(strict_types=1);

namespace App\Modules\Reference\Addresses\Data;

use App\Modules\Reference\Addresses\Models\Address;

/**
 * Shared address payload (`address: {...}` inside person / company requests).
 */
final readonly class AddressData
{
    /**
     * @param  array<string, string|float|null>  $values  Address::FIELDS => value (trimmed, empty → null)
     */
    private function __construct(public array $values) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        $values = [];
        foreach (Address::FIELDS as $field) {
            $value = $input[$field] ?? null;
            $value = is_string($value) ? trim(preg_replace('/\s+/u', ' ', $value) ?? '') : $value;
            $values[$field] = $value === '' ? null : (is_numeric($value) && in_array($field, ['latitude', 'longitude'], true) ? (float) $value : $value);
        }
        $values['country_code'] = strtoupper((string) $values['country_code']);

        return new self($values);
    }

    public function get(string $field): string|float|null
    {
        return $this->values[$field] ?? null;
    }

    /**
     * Validation rules of the nested payload, e.g. `AddressData::rules('address')`.
     *
     * @return array<string, list<string>>
     */
    public static function rules(string $prefix): array
    {
        $p = $prefix.'.';

        return [
            $prefix => ['nullable', 'array'],
            $p.'country_code' => ['required_with:'.$prefix, 'string', 'size:2'],
            $p.'flat' => ['nullable', 'string', 'max:20'],
            $p.'building' => ['nullable', 'string', 'max:20'],
            $p.'road' => ['nullable', 'string', 'max:20'],
            $p.'block' => ['nullable', 'string', 'max:10'],
            $p.'area' => ['nullable', 'string', 'max:100'],
            $p.'city' => ['nullable', 'string', 'max:100'],
            $p.'governorate' => ['nullable', 'string', 'max:100'],
            $p.'postal_code' => ['nullable', 'string', 'max:20'],
            $p.'line1' => ['nullable', 'string', 'max:255'],
            $p.'line2' => ['nullable', 'string', 'max:255'],
            $p.'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:'.$p.'longitude'],
            $p.'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:'.$p.'latitude'],
        ];
    }
}
