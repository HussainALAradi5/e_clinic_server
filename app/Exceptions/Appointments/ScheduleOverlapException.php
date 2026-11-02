<?php

declare(strict_types=1);

namespace App\Exceptions\Appointments;

use App\Exceptions\Shared\ConflictException;

/**
 * Two working blocks on the same day overlap.
 */
final class ScheduleOverlapException extends ConflictException
{
    protected string $errorCode = 'SCHEDULE_OVERLAP';
}
