<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Prescriptions\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * A medicine matches the patient's recorded allergies; confirm with `override_allergy = true`.
 */
final class AllergyWarningException extends ConflictException
{
    protected string $errorCode = 'ALLERGY_WARNING';
}
