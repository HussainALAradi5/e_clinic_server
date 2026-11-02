<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * The patient has no valid insurance for the services, or no line is insurable.
 */
final class InsuranceNotValidException extends BusinessValidationException
{
    protected string $errorCode = 'INSURANCE_NOT_VALID';
}
