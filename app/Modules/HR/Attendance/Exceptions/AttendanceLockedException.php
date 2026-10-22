<?php

declare(strict_types=1);

namespace App\Modules\HR\Attendance\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The month is in an approved payroll; its attendance is read-only.
 */
final class AttendanceLockedException extends ConflictException
{
    protected string $errorCode = 'ATTENDANCE_LOCKED';
}
