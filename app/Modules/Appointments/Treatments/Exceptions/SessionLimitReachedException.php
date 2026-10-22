<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Treatments\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * All planned sessions are already booked or done; raise the planned sessions first.
 */
final class SessionLimitReachedException extends BusinessValidationException
{
    protected string $errorCode = 'SESSION_LIMIT_REACHED';
}
