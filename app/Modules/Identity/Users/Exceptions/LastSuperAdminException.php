<?php

declare(strict_types=1);

namespace App\Modules\Identity\Users\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The change would leave no active super admin.
 */
final class LastSuperAdminException extends ConflictException
{
    protected string $errorCode = 'USER_LAST_SUPER_ADMIN';
}
