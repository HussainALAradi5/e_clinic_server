<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Records\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * A valid signed consent is needed before this can start.
 */
final class ConsentRequiredException extends BusinessValidationException
{
    protected string $errorCode = 'CONSENT_REQUIRED';
}
