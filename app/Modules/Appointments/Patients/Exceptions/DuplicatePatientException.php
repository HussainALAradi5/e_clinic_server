<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Patients\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * This person already has a patient file.
 */
final class DuplicatePatientException extends ConflictException
{
    protected string $errorCode = 'PATIENT_ALREADY_REGISTERED';
}
