<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\ForbiddenActionException;

/**
 * The caller tried to grant a permission or role they do not hold; `meta.permissions` lists them.
 */
final class PrivilegeEscalationException extends ForbiddenActionException
{
    protected string $errorCode = 'ROLE_PRIVILEGE_ESCALATION';
}
