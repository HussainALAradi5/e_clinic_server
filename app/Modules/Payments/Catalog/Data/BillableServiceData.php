<?php

declare(strict_types=1);

namespace App\Modules\Payments\Catalog\Data;

use Illuminate\Support\Arr;

/**
 * Price-list item input; on update only the provided keys change.
 */
final readonly class BillableServiceData
{
    private const FIELDS = ['code', 'name', 'name_ar', 'category', 'price', 'tax_rate', 'revenue_account_id', 'specialty_id', 'is_insurable', 'consent_type'];

    /**
     * @param  array<string, mixed>  $values
     */
    private function __construct(public array $values) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        $values = array_map(fn (mixed $v): mixed => $v === '' ? null : $v, Arr::only($input, self::FIELDS));
        foreach (['price', 'tax_rate'] as $decimal) {
            if (isset($values[$decimal])) {
                $values[$decimal] = (string) $values[$decimal];
            }
        }

        return new self($values);
    }
}
