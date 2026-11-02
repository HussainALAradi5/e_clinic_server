<?php

declare(strict_types=1);

namespace App\Dtos\Appointments;

use App\Enums\Shared\DayOfWeek;

/**
 * One weekly working block (`HH:MM` clinic local time).
 */
final readonly class ScheduleBlockData
{
    public function __construct(
        public DayOfWeek $dayOfWeek,
        public string $startTime,
        public string $endTime,
        public ?int $slotMinutes = null,
    ) {}

    public function overlaps(self $other): bool
    {
        return $this->dayOfWeek === $other->dayOfWeek && $this->startTime < $other->endTime && $other->startTime < $this->endTime;
    }
}
