<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Treatments\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The treatment is completed or cancelled; its sessions can no longer change.
 */
final class TreatmentNotOpenException extends ConflictException
{
    protected string $errorCode = 'TREATMENT_NOT_OPEN';
}
