<?php

declare(strict_types=1);

namespace App\Exceptions\HR;

use App\Exceptions\Shared\ForbiddenActionException;

/**
 * Only the employee who asked can withdraw a request.
 */
final class NotYourRequestException extends ForbiddenActionException
{
    protected string $errorCode = 'STAFF_REQUEST_NOT_YOURS';
}
