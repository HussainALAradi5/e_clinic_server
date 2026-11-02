<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

use App\Exceptions\Shared\ConflictException;

/**
 * Locked periods and years can never be reopened.
 */
final class FiscalPeriodLockedException extends ConflictException
{
    protected string $errorCode = 'FISCAL_PERIOD_LOCKED';
}
