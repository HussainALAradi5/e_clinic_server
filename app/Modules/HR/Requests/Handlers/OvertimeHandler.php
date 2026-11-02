<?php

declare(strict_types=1);

namespace App\Modules\HR\Requests\Handlers;

use App\Enums\HR\StaffRequestType;
use App\Modules\HR\Attendance\Contracts\AttendanceServiceInterface;
use App\Modules\HR\Attendance\Models\Attendance;
use App\Modules\HR\Requests\Contracts\StaffRequestHandler;
use App\Modules\HR\Support\AttendancePay;
use App\Shared\Support\MoneyHelper;
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
        $amount = $day->overtime_approved && $day->overtime_amount !== null ? $day->overtime_amount : $this->pay->overtime($day->employee, $day->overtime_minutes);

        return [
            'minutes' => $day->overtime_minutes,
            'rate' => (string) $this->pay->overtimeRate($day->employee)->toScale(3, RoundingMode::HALF_UP),
            'amount' => MoneyHelper::toApi($amount),
        ];
    }

    public function approve(Attendance $day): Attendance
    {
        return $this->attendance->approveOvertime($day);
    }
}
