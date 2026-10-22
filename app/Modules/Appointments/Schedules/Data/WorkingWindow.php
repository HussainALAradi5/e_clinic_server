<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Schedules\Data;

use Carbon\CarbonImmutable;

/**
 * A stretch of working time on one date (from weekly blocks or custom hours) and its slot length.
 */
final readonly class WorkingWindow
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public ?int $slotMinutes = null,
    ) {}

    public function contains(CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $start->gte($this->start) && $end->lte($this->end);
    }
}
