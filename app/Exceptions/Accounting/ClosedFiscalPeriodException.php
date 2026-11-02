<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

use App\Exceptions\Shared\ConflictException;

/**
 * The posting date has no open fiscal period (missing, closed or locked).
 */
final class ClosedFiscalPeriodException extends ConflictException
{
    protected string $errorCode = 'FISCAL_PERIOD_CLOSED';
}
