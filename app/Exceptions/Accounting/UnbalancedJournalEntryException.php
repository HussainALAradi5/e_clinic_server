<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * Total debits differ from total credits, or a line has both / neither side.
 */
final class UnbalancedJournalEntryException extends BusinessValidationException
{
    protected string $errorCode = 'JOURNAL_UNBALANCED';
}
