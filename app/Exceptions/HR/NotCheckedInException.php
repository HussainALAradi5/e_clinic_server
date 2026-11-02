<?php

declare(strict_types=1);

namespace App\Exceptions\HR;

use App\Exceptions\Shared\ConflictException;

/**
 * Check-out without a check-in today (or already checked out).
 */
final class NotCheckedInException extends ConflictException
{
    protected string $errorCode = 'ATTENDANCE_NOT_CHECKED_IN';
}
