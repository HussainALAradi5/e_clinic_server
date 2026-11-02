<?php

declare(strict_types=1);

namespace App\Exceptions\Pharmacy;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * A prescription-only medicine was dispensed without a prescription.
 */
final class MedicationRequiresPrescriptionException extends BusinessValidationException
{
    protected string $errorCode = 'MEDICATION_REQUIRES_PRESCRIPTION';
}
