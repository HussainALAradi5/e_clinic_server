<?php

declare(strict_types=1);

namespace App\Modules\HR\Attendance\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * You already checked in today.
 */
final class AlreadyCheckedInException extends ConflictException
{
    protected string $errorCode = 'ATTENDANCE_ALREADY_CHECKED_IN';
}
