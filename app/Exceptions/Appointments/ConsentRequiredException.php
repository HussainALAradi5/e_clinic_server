<?php

declare(strict_types=1);

namespace App\Exceptions\Appointments;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * A valid signed consent is needed before this can start.
 */
final class ConsentRequiredException extends BusinessValidationException
{
    protected string $errorCode = 'CONSENT_REQUIRED';
}
