<?php

declare(strict_types=1);

namespace App\Modules\HR\Leaves\Listeners;

use App\Modules\HR\Employees\Events\EmployeeHired;
use App\Modules\HR\Leaves\Contracts\LeaveServiceInterface;
use App\Shared\Support\ClinicClock;

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
