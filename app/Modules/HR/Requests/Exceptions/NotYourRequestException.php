<?php

declare(strict_types=1);

namespace App\Modules\HR\Requests\Exceptions;

use App\Shared\Exceptions\ForbiddenActionException;

/**
 * Only the employee who asked can withdraw a request.
 */
final class NotYourRequestException extends ForbiddenActionException
{
    protected string $errorCode = 'STAFF_REQUEST_NOT_YOURS';
}
