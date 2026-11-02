<?php

declare(strict_types=1);

namespace App\Listeners\HR;

use App\Events\HR\EmployeeHired;
use App\Interfaces\HR\LeaveServiceInterface;
use App\Utilities\Shared\ClinicClock;

/**
 * A new employee starts with this year's leave balances (quota pro-rated from the hire month).
 */
final class OpenLeaveBalances
{
    public function __construct(private readonly LeaveServiceInterface $leaves, private readonly ClinicClock $clock) {}

    public function handle(EmployeeHired $event): void
    {
        $this->leaves->initializeBalances($event->employee, max($this->clock->today()->year, $event->employee->hire_date->year));
    }
}
