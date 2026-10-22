<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Prescriptions\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The patient already has an open prescription for the same medicine; confirm with `override_duplicate = true`.
 */
final class DuplicateTherapyWarningException extends ConflictException
{
    protected string $errorCode = 'DUPLICATE_THERAPY';
}
