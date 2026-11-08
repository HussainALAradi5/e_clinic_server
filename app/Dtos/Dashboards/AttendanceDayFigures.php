<?php

declare(strict_types=1);

namespace App\Dtos\Dashboards;

/** One clinic day of attendance in figures. */
final readonly class AttendanceDayFigures
{
    public function __construct(
        public int $present,
        public int $late,
        public int $absent,
        public int $onLeave,
        public int $pendingOvertime,
        public int $approvedOvertimeMinutes,
        public int $needsReview,
    ) {}
}
