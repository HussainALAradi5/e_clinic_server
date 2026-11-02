<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\ConflictException;

/**
 * The role is still assigned to users.
 */
final class RoleInUseException extends ConflictException
{
    protected string $errorCode = 'ROLE_IN_USE';
}
