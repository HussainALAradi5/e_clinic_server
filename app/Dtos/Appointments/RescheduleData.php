<?php

declare(strict_types=1);

namespace App\Dtos\Appointments;

use Carbon\CarbonImmutable;

/**
 * New time (and optionally another provider / room) for an existing booking.
 */
final readonly class RescheduleData
{
    public function __construct(
        public CarbonImmutable $date,
        public string $startTime,
        public ?string $providerId = null,
        public ?string $roomId = null,
        public ?string $reason = null,
    ) {}
}
