<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Exceptions;

use App\Shared\Exceptions\ForbiddenActionException;

/**
 * Account status is inactive or pending verification.
 */
final class AccountInactiveException extends ForbiddenActionException
{
    protected string $errorCode = 'AUTH_ACCOUNT_INACTIVE';
}
