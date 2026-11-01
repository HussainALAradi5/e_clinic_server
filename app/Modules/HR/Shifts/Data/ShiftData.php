<?php

declare(strict_types=1);

namespace App\Modules\HR\Shifts\Data;

use App\Enums\HR\ShiftType;
use Illuminate\Support\Arr;

/**
 * Shift input; on update only the provided keys change. A fixed shift's hours due are its length, a flexible one
 * has no arrive-by time stored when fixed.
 */
final readonly class ShiftData
{
    private const FIELDS = ['name', 'type', 'start_time', 'end_time', 'latest_start', 'required_minutes', 'notes'];

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
        if (($values['type'] ?? null) === ShiftType::Fixed->value) {
            $values['latest_start'] = null;
            if (isset($values['start_time'], $values['end_time'])) {
                $values['required_minutes'] = self::minutes((string) $values['end_time']) - self::minutes((string) $values['start_time']);
            }
        }

        return new self($values);
    }

    private static function minutes(string $time): int
    {
        [$h, $m] = array_map(intval(...), explode(':', $time));

        return $h * 60 + $m;
    }
}
