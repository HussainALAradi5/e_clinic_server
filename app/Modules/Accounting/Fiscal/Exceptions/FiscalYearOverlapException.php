<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Fiscal\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * Fiscal year dates overlap an existing year.
 */
final class FiscalYearOverlapException extends ConflictException
{
    protected string $errorCode = 'FISCAL_YEAR_OVERLAP';
}
