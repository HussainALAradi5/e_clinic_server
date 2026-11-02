<?php

declare(strict_types=1);

namespace App\Dtos\Appointments;

use Illuminate\Support\Arr;

final readonly class RoomData
{
    private const FIELDS = ['code', 'name', 'name_ar', 'type', 'department_id', 'floor', 'capacity', 'notes'];

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
