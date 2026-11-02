<?php

declare(strict_types=1);

namespace App\Modules\HR\Requests\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * HR has switched this kind of request off.
 */
final class StaffRequestDisabledException extends ConflictException
{
    protected string $errorCode = 'STAFF_REQUEST_DISABLED';
}
