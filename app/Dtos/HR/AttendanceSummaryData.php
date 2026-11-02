<?php

declare(strict_types=1);

namespace App\Dtos\HR;

use App\Utilities\Shared\MoneyHelper;
use Brick\Money\Money;

/**
 * One employee's month as payroll needs it (days within their employment only).
 */
final readonly class AttendanceSummaryData
{
    public function __construct(
        public int $workingDays,
        public string $presentDays,
        public string $absentDays,
        public string $paidLeaveDays,
        public string $unpaidLeaveDays,
        public int $lateMinutes,
        public int $approvedOvertimeMinutes,
        public int $pendingOvertimeMinutes,
        public int $unexcusedLateMinutes,
        public Money $approvedOvertimeAmount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'working_days' => $this->workingDays,
            'present_days' => $this->presentDays,
            'absent_days' => $this->absentDays,
            'paid_leave_days' => $this->paidLeaveDays,
            'unpaid_leave_days' => $this->unpaidLeaveDays,
            'late_minutes' => $this->lateMinutes,
            'approved_overtime_minutes' => $this->approvedOvertimeMinutes,
            'pending_overtime_minutes' => $this->pendingOvertimeMinutes,
            'unexcused_late_minutes' => $this->unexcusedLateMinutes,
            'approved_overtime_amount' => MoneyHelper::toApi($this->approvedOvertimeAmount),
        ];
    }
}
