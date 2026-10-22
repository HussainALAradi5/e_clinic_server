<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Fiscal\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The posting date has no open fiscal period (missing, closed or locked).
 */
final class ClosedFiscalPeriodException extends ConflictException
{
    protected string $errorCode = 'FISCAL_PERIOD_CLOSED';
}
