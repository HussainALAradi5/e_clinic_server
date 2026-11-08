<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\ForbiddenActionException;

/**
 * The caller may read the event log but not the kind of record this log belongs to.
 */
final class ActivityEntityForbiddenException extends ForbiddenActionException
{
    protected string $errorCode = 'ACTIVITY_ENTITY_FORBIDDEN';
}
