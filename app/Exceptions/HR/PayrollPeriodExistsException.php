<?php

declare(strict_types=1);

namespace App\Exceptions\HR;

use App\Exceptions\Shared\ConflictException;

/**
 * A payroll run (not cancelled) already exists for that month.
 */
final class PayrollPeriodExistsException extends ConflictException
{
    protected string $errorCode = 'PAYROLL_PERIOD_EXISTS';
}
