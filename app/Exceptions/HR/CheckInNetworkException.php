<?php

declare(strict_types=1);

namespace App\Exceptions\HR;

use App\Exceptions\Shared\ForbiddenActionException;

/**
 * Web check-in / check-out only from the clinic network.
 */
final class CheckInNetworkException extends ForbiddenActionException
{
    protected string $errorCode = 'ATTENDANCE_NETWORK_NOT_ALLOWED';
}
