<?php

declare(strict_types=1);

namespace App\Modules\HR\Shifts\Exceptions;

use App\Shared\Exceptions\ConflictException;

/** A shift with people on it cannot be deleted: move them first (or archive it). */
final class ShiftHasEmployeesException extends ConflictException
{
    protected string $errorCode = 'SHIFT_HAS_EMPLOYEES';
}
