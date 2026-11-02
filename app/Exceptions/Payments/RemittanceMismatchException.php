<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * Remittances cover approved, unremitted claims of one insurer for exactly their approved total.
 */
final class RemittanceMismatchException extends BusinessValidationException
{
    protected string $errorCode = 'CLAIM_REMITTANCE_MISMATCH';
}
