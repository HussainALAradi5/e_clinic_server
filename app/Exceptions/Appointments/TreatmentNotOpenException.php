<?php

declare(strict_types=1);

namespace App\Exceptions\Appointments;

use App\Exceptions\Shared\ConflictException;

/**
 * The treatment is completed or cancelled; its sessions can no longer change.
 */
final class TreatmentNotOpenException extends ConflictException
{
    protected string $errorCode = 'TREATMENT_NOT_OPEN';
}
