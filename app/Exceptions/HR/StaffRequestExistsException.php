<?php

declare(strict_types=1);

namespace App\Exceptions\HR;

use App\Exceptions\Shared\ConflictException;

/**
 * The day already has a request of this kind (pending, approved or rejected).
 */
final class StaffRequestExistsException extends ConflictException
{
    protected string $errorCode = 'STAFF_REQUEST_EXISTS';
}
