<?php

declare(strict_types=1);

namespace App\Modules\HR\Departments\Data;

use Illuminate\Support\Arr;

/**
 * Department input; on update only the provided keys change.
 */
final readonly class DepartmentData
{
    private const FIELDS = ['code', 'name', 'name_ar', 'parent_id', 'manager_id', 'cost_center_code'];

    /**
     * @param  array<string, mixed>  $values
     */
    private function __construct(public array $values) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        return new self(array_map(fn (mixed $v): mixed => $v === '' ? null : $v, Arr::only($input, self::FIELDS)));
    }
}
