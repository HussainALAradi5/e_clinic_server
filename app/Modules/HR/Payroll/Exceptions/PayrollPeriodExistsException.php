<?php

declare(strict_types=1);

namespace App\Modules\HR\Payroll\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * A payroll run (not cancelled) already exists for that month.
 */
final class PayrollPeriodExistsException extends ConflictException
{
    protected string $errorCode = 'PAYROLL_PERIOD_EXISTS';
}
