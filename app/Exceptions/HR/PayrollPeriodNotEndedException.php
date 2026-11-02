<?php

declare(strict_types=1);

namespace App\Exceptions\HR;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * A month's payroll is calculated only after its last day.
 */
final class PayrollPeriodNotEndedException extends BusinessValidationException
{
    protected string $errorCode = 'PAYROLL_PERIOD_NOT_ENDED';
}
