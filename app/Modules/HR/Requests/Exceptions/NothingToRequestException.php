<?php

declare(strict_types=1);

namespace App\Modules\HR\Requests\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * The day has no late minutes / overtime to ask about, or they are already settled.
 */
final class NothingToRequestException extends BusinessValidationException
{
    protected string $errorCode = 'STAFF_REQUEST_NOTHING_TO_ASK';
}
