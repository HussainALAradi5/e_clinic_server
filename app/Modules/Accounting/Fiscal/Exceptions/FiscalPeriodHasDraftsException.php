<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Fiscal\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The period still has draft journal entries, draft invoices or open cash sessions.
 */
final class FiscalPeriodHasDraftsException extends ConflictException
{
    protected string $errorCode = 'FISCAL_PERIOD_HAS_DRAFTS';
}
