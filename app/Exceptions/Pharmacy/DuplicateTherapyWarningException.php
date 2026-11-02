<?php

declare(strict_types=1);

namespace App\Exceptions\Pharmacy;

use App\Exceptions\Shared\ConflictException;

/**
 * The patient already has an open prescription for the same medicine; confirm with `override_duplicate = true`.
 */
final class DuplicateTherapyWarningException extends ConflictException
{
    protected string $errorCode = 'DUPLICATE_THERAPY';
}
