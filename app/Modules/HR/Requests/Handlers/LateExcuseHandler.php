<?php

declare(strict_types=1);

namespace App\Modules\HR\Requests\Handlers;

use App\Enums\HR\StaffRequestType;
use App\Modules\HR\Attendance\Contracts\AttendanceServiceInterface;
use App\Modules\HR\Attendance\Models\Attendance;
use App\Modules\HR\Requests\Contracts\StaffRequestHandler;
use App\Modules\HR\Support\AttendancePay;
use App\Shared\Support\MoneyHelper;

/** Excuse a late arrival: approved, its minutes are no longer deducted from pay. */
final class LateExcuseHandler implements StaffRequestHandler
{
    public function __construct(
        private readonly AttendanceServiceInterface $attendance,
        private readonly AttendancePay $pay,
    ) {}

    public function type(): StaffRequestType
    {
        return StaffRequestType::LateExcuse;
    }

    public function minutes(Attendance $day): int
    {
        return $day->late_minutes;
    }

    public function isSettled(Attendance $day): bool
    {
        return $day->late_excused;
    }

    public function details(Attendance $day): array
    {
        return [
            'minutes' => $day->late_minutes,
            // What the late arrival would cost if not excused.
            'amount' => MoneyHelper::toApi($this->pay->lateness($day->employee, $day->late_minutes)),
        ];
    }

    public function approve(Attendance $day): Attendance
    {
        return $this->attendance->excuseLate($day);
    }
}
