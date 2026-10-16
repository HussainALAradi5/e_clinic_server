<?php

declare(strict_types=1);

namespace App\Modules\Identity\Users\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * Users cannot change their own status or roles.
 */
final class CannotModifySelfException extends ConflictException
{
    protected string $errorCode = 'USER_CANNOT_MODIFY_SELF';
}
