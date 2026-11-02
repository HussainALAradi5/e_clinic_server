<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

use App\Exceptions\Shared\ConflictException;

/**
 * Periods close in order and reopen in reverse order.
 */
final class PreviousPeriodOpenException extends ConflictException
{
    protected string $errorCode = 'PREVIOUS_PERIOD_OPEN';
}
