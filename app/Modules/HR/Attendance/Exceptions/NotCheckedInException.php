<?php

declare(strict_types=1);

namespace App\Modules\HR\Attendance\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * Check-out without a check-in today (or already checked out).
 */
final class NotCheckedInException extends ConflictException
{
    protected string $errorCode = 'ATTENDANCE_NOT_CHECKED_IN';
}
