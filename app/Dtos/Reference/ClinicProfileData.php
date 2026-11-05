<?php

declare(strict_types=1);

namespace App\Dtos\Reference;

use Illuminate\Support\Arr;

/**
 * Editable clinic profile fields; only provided keys are applied.
 */
final readonly class ClinicProfileData
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
        return Arr::only($this->values, ['name', 'tagline', 'address', 'country_code', 'phone', 'email', 'website']);
    }
}
