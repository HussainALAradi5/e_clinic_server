<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\ConflictException;

/**
 * Users cannot change their own status or roles.
 */
final class CannotModifySelfException extends ConflictException
{
    protected string $errorCode = 'USER_CANNOT_MODIFY_SELF';
}
