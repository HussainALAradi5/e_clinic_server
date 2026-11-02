<?php

declare(strict_types=1);

namespace App\Dtos\Appointments;

use App\Enums\Appointments\ScheduleExceptionType;
use Carbon\CarbonImmutable;

final readonly class ScheduleExceptionData
{
    public function __construct(
        public CarbonImmutable $date,
        public ScheduleExceptionType $type,
        public ?string $startTime = null,
        public ?string $endTime = null,
        public ?string $reason = null,
        public bool $cancelAffected = false,
    ) {}
}
