<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

use App\Exceptions\Shared\ConflictException;

/**
 * Fiscal year dates overlap an existing year.
 */
final class FiscalYearOverlapException extends ConflictException
{
    protected string $errorCode = 'FISCAL_YEAR_OVERLAP';
}
