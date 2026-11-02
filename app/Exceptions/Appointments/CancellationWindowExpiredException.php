<?php

declare(strict_types=1);

namespace App\Exceptions\Appointments;

use App\Exceptions\Shared\ConflictException;

/**
 * It is too late to change this appointment online. Please call the clinic.
 */
final class CancellationWindowExpiredException extends ConflictException
{
    protected string $errorCode = 'CANCELLATION_WINDOW_EXPIRED';
}
