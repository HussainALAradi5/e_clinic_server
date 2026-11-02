<?php

declare(strict_types=1);

namespace App\Exceptions\HR;

use App\Exceptions\Shared\ConflictException;

/**
 * Overtime requests are on: overtime is approved through the employee's request.
 */
final class OvertimeNeedsRequestException extends ConflictException
{
    protected string $errorCode = 'OVERTIME_NEEDS_REQUEST';
}
