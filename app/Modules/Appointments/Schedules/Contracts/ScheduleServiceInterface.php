<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Schedules\Contracts;

use App\Modules\Appointments\Schedules\Data\ScheduleBlockData;
use App\Modules\Appointments\Schedules\Data\ScheduleExceptionData;
use App\Modules\Appointments\Schedules\Data\WorkingWindow;
use App\Modules\Appointments\Schedules\Models\ProviderSchedule;
use App\Modules\Appointments\Schedules\Models\ScheduleException;
use App\Modules\HR\Employees\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

interface ScheduleServiceInterface
{
    /**
     * Blocks valid on a date (default today), ordered by weekday and time.
     *
     * @return Collection<int, ProviderSchedule>
     */
    public function getWeeklySchedule(Employee $provider, ?CarbonImmutable $onDate = null): Collection;

    /**
     * Replaces the week from a date onward; blocks may not overlap and booked appointments may not fall outside.
     *
     * @param  list<ScheduleBlockData>  $blocks
     * @return Collection<int, ProviderSchedule>
     */
    public function replaceWeeklySchedule(Employee $provider, array $blocks, CarbonImmutable $effectiveFrom): Collection;

    /**
     * Provider exceptions and clinic-wide holidays in a range (provider null = holidays only).
     *
     * @return Collection<int, ScheduleException>
     */
    public function listExceptions(?Employee $provider, CarbonImmutable $from, CarbonImmutable $to): Collection;

    /** Day off / custom hours (provider) or holiday (provider null); affected bookings block it unless cancelled. */
    public function addException(?Employee $provider, ScheduleExceptionData $data): ScheduleException;

    public function removeException(ScheduleException $exception): void;

    /**
     * Internal — effective working windows on a date (holiday / day off → none, custom hours → that window).
     *
     * @return Collection<int, WorkingWindow>
     */
    public function getWorkingWindows(Employee $provider, CarbonImmutable $date): Collection;
}
