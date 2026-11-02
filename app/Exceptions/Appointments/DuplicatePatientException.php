<?php

declare(strict_types=1);

namespace App\Exceptions\Appointments;

use App\Exceptions\Shared\ConflictException;

/**
 * This person already has a patient file.
 */
final class DuplicatePatientException extends ConflictException
{
    protected string $errorCode = 'PATIENT_ALREADY_REGISTERED';
}
