<?php

declare(strict_types=1);

namespace App\Exceptions\Appointments;

use App\Exceptions\Shared\ConflictException;

/**
 * This patient already has a portal account.
 */
final class PatientAlreadyHasAccountException extends ConflictException
{
    protected string $errorCode = 'PATIENT_HAS_ACCOUNT';
}
