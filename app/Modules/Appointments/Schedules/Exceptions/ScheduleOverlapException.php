<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Schedules\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * Two working blocks on the same day overlap.
 */
final class ScheduleOverlapException extends ConflictException
{
    protected string $errorCode = 'SCHEDULE_OVERLAP';
}
