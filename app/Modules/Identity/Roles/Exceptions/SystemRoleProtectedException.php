<?php

declare(strict_types=1);

namespace App\Modules\Identity\Roles\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * System roles cannot be renamed or deleted (and super_admin's rights are read-only).
 */
final class SystemRoleProtectedException extends ConflictException
{
    protected string $errorCode = 'ROLE_SYSTEM_PROTECTED';
}
