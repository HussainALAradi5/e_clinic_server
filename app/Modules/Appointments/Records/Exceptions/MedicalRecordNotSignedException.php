<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Records\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * Addenda are for signed notes; edit the note itself while it is unsigned.
 */
final class MedicalRecordNotSignedException extends ConflictException
{
    protected string $errorCode = 'MEDICAL_RECORD_NOT_SIGNED';
}
