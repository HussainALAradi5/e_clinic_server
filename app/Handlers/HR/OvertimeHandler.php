<?php

declare(strict_types=1);

namespace App\Handlers\HR;

use App\Enums\HR\StaffRequestType;
use App\Interfaces\HR\AttendanceServiceInterface;
use App\Interfaces\HR\StaffRequestHandler;
use App\Models\HR\Attendance;
use App\Utilities\HR\AttendancePay;
use App\Utilities\Shared\MoneyHelper;
use Brick\Math\RoundingMode;

/** Pay the overtime of a day: approved, the attendance service approves it and keeps its amount on the day. */
final class OvertimeHandler implements StaffRequestHandler
{
    public function __construct(
        private readonly AttendanceServiceInterface $attendance,
        private readonly AttendancePay $pay,
    ) {}

    public function type(): StaffRequestType
    {
        return StaffRequestType::Overtime;
    }

    public function minutes(Attendance $day): int
    {
        return $day->overtime_minutes;
    }

    public function isSettled(Attendance $day): bool
    {
        return $day->overtime_approved;
    }

    public function details(Attendance $day): array
    {
        $amount = $day->overtime_approved && $day->overtime_amount !== null ? $day->overtime_amount : $this->pay->overtime($day->employee, $day);

        return [
            'minutes' => $day->overtime_minutes,
            'rate' => (string) $this->pay->overtimeRateForDay($day->employee, $day)->toScale(3, RoundingMode::HALF_UP),
            'amount' => MoneyHelper::toApi($amount),
        ];
    }

    public function approve(Attendance $day): Attendance
    {
        return $this->attendance->approveOvertime($day);
    }
}
