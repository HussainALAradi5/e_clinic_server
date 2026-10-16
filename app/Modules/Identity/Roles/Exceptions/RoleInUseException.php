<?php

declare(strict_types=1);

namespace App\Modules\Identity\Roles\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The role is still assigned to users.
 */
final class RoleInUseException extends ConflictException
{
    protected string $errorCode = 'ROLE_IN_USE';
}
