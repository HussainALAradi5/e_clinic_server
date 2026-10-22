<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Dispensing\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * A prescription-only medicine was dispensed without a prescription.
 */
final class MedicationRequiresPrescriptionException extends BusinessValidationException
{
    protected string $errorCode = 'MEDICATION_REQUIRES_PRESCRIPTION';
}
