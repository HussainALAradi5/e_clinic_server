<?php

declare(strict_types=1);

namespace App\Shared\Query;

use App\Shared\Enums\DateRangePreset;
use Carbon\CarbonImmutable;

/**
 * Inclusive clinic-local date range on one whitelisted date field.
 */
final readonly class DateFilter
{
    public function __construct(
        public string $field,
        public ?CarbonImmutable $from,
        public ?CarbonImmutable $to,
        public ?DateRangePreset $preset = null,
    ) {}

    /**
     * @return array{field: string, from: ?string, to: ?string, preset: ?string}
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
            'preset' => $this->preset?->value,
        ];
    }
}
