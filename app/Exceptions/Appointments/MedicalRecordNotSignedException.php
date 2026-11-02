<?php

declare(strict_types=1);

namespace App\Exceptions\Appointments;

use App\Exceptions\Shared\ConflictException;

/**
 * Addenda are for signed notes; edit the note itself while it is unsigned.
 */
final class MedicalRecordNotSignedException extends ConflictException
{
    protected string $errorCode = 'MEDICAL_RECORD_NOT_SIGNED';
}
