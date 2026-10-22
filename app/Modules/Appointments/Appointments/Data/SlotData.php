<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Appointments\Data;

use Carbon\CarbonImmutable;

/**
 * A free bookable slot.
 */
final readonly class SlotData
{
    public function __construct(public CarbonImmutable $start, public CarbonImmutable $end) {}

    /**
     * @return array{start: string, end: string, start_time: string, end_time: string}
     */
    public function toArray(): array
    {
        return [
            'start' => $this->start->format('Y-m-d\TH:i:s'),
            'end' => $this->end->format('Y-m-d\TH:i:s'),
            'start_time' => $this->start->format('H:i'),
            'end_time' => $this->end->format('H:i'),
        ];
    }
}
