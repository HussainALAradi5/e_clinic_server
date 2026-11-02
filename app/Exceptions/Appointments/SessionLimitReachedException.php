<?php

declare(strict_types=1);

namespace App\Exceptions\Appointments;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * All planned sessions are already booked or done; raise the planned sessions first.
 */
final class SessionLimitReachedException extends BusinessValidationException
{
    protected string $errorCode = 'SESSION_LIMIT_REACHED';
}
