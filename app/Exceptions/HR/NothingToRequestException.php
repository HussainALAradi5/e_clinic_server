<?php

declare(strict_types=1);

namespace App\Exceptions\HR;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * The day has no late minutes / overtime to ask about, or they are already settled.
 */
final class NothingToRequestException extends BusinessValidationException
{
    protected string $errorCode = 'STAFF_REQUEST_NOTHING_TO_ASK';
}
