<?php

declare(strict_types=1);

namespace App\Exceptions\HR;

use App\Exceptions\Shared\ConflictException;

/**
 * The month is in an approved payroll; its attendance is read-only.
 */
final class AttendanceLockedException extends ConflictException
{
    protected string $errorCode = 'ATTENDANCE_LOCKED';
}
