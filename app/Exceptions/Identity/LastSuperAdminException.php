<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\ConflictException;

/**
 * The change would leave no active super admin.
 */
final class LastSuperAdminException extends ConflictException
{
    protected string $errorCode = 'USER_LAST_SUPER_ADMIN';
}
