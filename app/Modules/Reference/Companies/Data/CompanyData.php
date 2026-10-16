<?php

declare(strict_types=1);

namespace App\Modules\Reference\Companies\Data;

use App\Modules\Reference\Addresses\Data\AddressData;
use Illuminate\Support\Arr;

/**
 * Company input; on update only provided keys change. `address` provided as null removes the address.
 */
final readonly class CompanyData
{
    private const FIELDS = ['type', 'name', 'name_ar', 'description', 'country_code', 'phone', 'email', 'website', 'contact_person', 'vat_number', 'cr_number'];

    /**
     * @param  array<string, mixed>  $values
     */
    private function __construct(
        public array $values,
        public bool $addressProvided,
        public ?AddressData $address,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        $values = Arr::only($input, self::FIELDS);
        if (isset($values['country_code'])) {
            $values['country_code'] = strtoupper((string) $values['country_code']);
        }

        return new self(
            values: array_map(fn (mixed $v): mixed => $v === '' ? null : $v, $values),
            addressProvided: array_key_exists('address', $input),
            address: is_array($input['address'] ?? null) ? AddressData::fromArray($input['address']) : null,
        );
    }
}
