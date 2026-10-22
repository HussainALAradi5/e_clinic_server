<?php

declare(strict_types=1);

namespace App\Modules\Payments\Claims\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * Remittances cover approved, unremitted claims of one insurer for exactly their approved total.
 */
final class RemittanceMismatchException extends BusinessValidationException
{
    protected string $errorCode = 'CLAIM_REMITTANCE_MISMATCH';
}
