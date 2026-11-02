<?php

declare(strict_types=1);

namespace App\Exceptions\Communication;

use App\Exceptions\Shared\ForbiddenActionException;

/** Only an admin of the group can change it. */
final class GroupAdminRequiredException extends ForbiddenActionException
{
    protected string $errorCode = 'CHAT_GROUP_ADMIN_REQUIRED';
}
