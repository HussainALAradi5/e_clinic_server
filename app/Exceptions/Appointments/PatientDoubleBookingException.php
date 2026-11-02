<?php

declare(strict_types=1);

namespace App\Exceptions\Appointments;

use App\Exceptions\Shared\ConflictException;

/**
 * The patient already has an appointment at that time.
 */
final class PatientDoubleBookingException extends ConflictException
{
    protected string $errorCode = 'PATIENT_DOUBLE_BOOKED';
}
