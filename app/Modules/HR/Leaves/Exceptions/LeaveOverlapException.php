<?php

declare(strict_types=1);

namespace App\Modules\HR\Leaves\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The employee already has a pending or approved leave in that period.
 */
final class LeaveOverlapException extends ConflictException
{
    protected string $errorCode = 'LEAVE_OVERLAP';
}
