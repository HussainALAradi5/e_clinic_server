<?php

declare(strict_types=1);

namespace App\Enums\Dashboards;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/** Keys of the attendance section's metrics; a key is stored in a dashboard's `widgets`, so it stays stable. */
enum AttendanceMetric: string implements HasLabel
{
    use EnumHelpers;

    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case OnLeave = 'on_leave';
    case PendingOvertime = 'pending_overtime';
    case ApprovedOvertimeMinutes = 'approved_overtime_minutes';
    case NeedsReview = 'needs_review';
    case LateArrivals = 'late_arrivals';
}
