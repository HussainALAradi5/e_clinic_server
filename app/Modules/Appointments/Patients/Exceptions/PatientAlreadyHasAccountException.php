<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Patients\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * This patient already has a portal account.
 */
final class PatientAlreadyHasAccountException extends ConflictException
{
    protected string $errorCode = 'PATIENT_HAS_ACCOUNT';
}
