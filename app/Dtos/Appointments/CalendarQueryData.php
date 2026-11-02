<?php

declare(strict_types=1);

namespace App\Dtos\Appointments;

use Carbon\CarbonImmutable;

final readonly class CalendarQueryData
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?string $providerId = null,
        public ?string $roomId = null,
    ) {}
}
