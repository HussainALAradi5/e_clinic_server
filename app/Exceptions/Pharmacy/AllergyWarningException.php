<?php

declare(strict_types=1);

namespace App\Exceptions\Pharmacy;

use App\Exceptions\Shared\ConflictException;

/**
 * A medicine matches the patient's recorded allergies; confirm with `override_allergy = true`.
 */
final class AllergyWarningException extends ConflictException
{
    protected string $errorCode = 'ALLERGY_WARNING';
}
