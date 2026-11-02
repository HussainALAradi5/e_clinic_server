<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

use App\Exceptions\Shared\ConflictException;

/**
 * The period still has draft journal entries, draft invoices or open cash sessions.
 */
final class FiscalPeriodHasDraftsException extends ConflictException
{
    protected string $errorCode = 'FISCAL_PERIOD_HAS_DRAFTS';
}
