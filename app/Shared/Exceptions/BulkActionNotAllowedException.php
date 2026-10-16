<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * Unknown bulk action for the resource, or the user lacks its permission.
 */
final class BulkActionNotAllowedException extends ForbiddenActionException
{
    protected string $errorCode = 'BULK_ACTION_NOT_ALLOWED';
}
