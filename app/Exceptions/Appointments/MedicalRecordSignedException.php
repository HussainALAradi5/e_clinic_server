<?php

declare(strict_types=1);

namespace App\Exceptions\Appointments;

use App\Exceptions\Shared\ConflictException;

/**
 * The visit note is signed; add an addendum instead of editing it.
 */
final class MedicalRecordSignedException extends ConflictException
{
    protected string $errorCode = 'MEDICAL_RECORD_SIGNED';
}
