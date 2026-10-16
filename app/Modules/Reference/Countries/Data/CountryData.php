<?php

declare(strict_types=1);

namespace App\Modules\Reference\Countries\Data;

use Illuminate\Support\Arr;

/**
 * Editable country fields; only provided keys are applied.
 */
final readonly class CountryData
{
    /**
     * @param  array<string, mixed>  $values  validated snake_case input
     */
    public function __construct(public array $values) {}

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return Arr::only($this->values, ['name', 'name_ar', 'nationality', 'nationality_ar', 'phone_code', 'is_active', 'sort_order']);
    }
}
