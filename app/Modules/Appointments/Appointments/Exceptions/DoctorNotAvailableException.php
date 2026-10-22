<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Appointments\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The doctor is not taking bookings on that date.
 */
final class DoctorNotAvailableException extends ConflictException
{
    protected string $errorCode = 'DOCTOR_NOT_AVAILABLE';
}
