<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * The current password supplied is wrong.
 */
final class CurrentPasswordMismatchException extends BusinessValidationException
{
    protected string $errorCode = 'AUTH_CURRENT_PASSWORD_WRONG';
}
