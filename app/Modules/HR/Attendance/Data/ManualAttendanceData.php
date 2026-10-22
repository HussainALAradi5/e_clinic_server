<?php

declare(strict_types=1);

namespace App\Modules\HR\Attendance\Data;

use App\Enums\HR\AttendanceStatus;
use Carbon\CarbonImmutable;

/**
 * HR correction of one day: times ('H:i', clinic time) or, without a check-in, the day result; a reason is required.
 */
final readonly class ManualAttendanceData
{
    public function __construct(
        public string $employeeId,
        public CarbonImmutable $date,
        public ?string $checkIn,
        public ?string $checkOut,
        public ?AttendanceStatus $status,
        public string $reason,
    ) {}
}
