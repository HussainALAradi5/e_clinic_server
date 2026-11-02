<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * Every visit on the claim needs a signed note with a primary ICD-10 diagnosis.
 */
final class ClaimMissingDiagnosisException extends BusinessValidationException
{
    protected string $errorCode = 'CLAIM_MISSING_DIAGNOSIS';
}
