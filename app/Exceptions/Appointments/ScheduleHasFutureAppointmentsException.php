<?php

declare(strict_types=1);

namespace App\Exceptions\Appointments;

use App\Exceptions\Shared\ConflictException;

/**
 * This change would leave booked appointments outside working hours. Reschedule or cancel them first.
 */
final class ScheduleHasFutureAppointmentsException extends ConflictException
{
    protected string $errorCode = 'SCHEDULE_HAS_APPOINTMENTS';
}
