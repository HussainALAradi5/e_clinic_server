<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

use App\Exceptions\Shared\ConflictException;

/**
 * A system or mapped account cannot be deleted or archived.
 */
final class SystemAccountProtectedException extends ConflictException
{
    protected string $errorCode = 'ACCOUNT_SYSTEM_PROTECTED';
}
