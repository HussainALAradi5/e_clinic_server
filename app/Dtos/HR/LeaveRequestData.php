<?php

declare(strict_types=1);

namespace App\Dtos\HR;

use Carbon\CarbonImmutable;

/**
 * A leave request: type, dates (one calendar year), optional half day (single date) and reason.
 */
final readonly class LeaveRequestData
{
    public function __construct(
        public string $leaveTypeId,
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
        public bool $isHalfDay = false,
        public ?string $reason = null,
    ) {}
}
