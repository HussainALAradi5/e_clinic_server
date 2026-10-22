<?php

declare(strict_types=1);

namespace App\Modules\Payments\Claims\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * Every visit on the claim needs a signed note with a primary ICD-10 diagnosis.
 */
final class ClaimMissingDiagnosisException extends BusinessValidationException
{
    protected string $errorCode = 'CLAIM_MISSING_DIAGNOSIS';
}
