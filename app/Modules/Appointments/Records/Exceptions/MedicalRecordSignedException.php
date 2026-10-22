<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Records\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The visit note is signed; add an addendum instead of editing it.
 */
final class MedicalRecordSignedException extends ConflictException
{
    protected string $errorCode = 'MEDICAL_RECORD_SIGNED';
}
