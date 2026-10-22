<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Journal\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * Total debits differ from total credits, or a line has both / neither side.
 */
final class UnbalancedJournalEntryException extends BusinessValidationException
{
    protected string $errorCode = 'JOURNAL_UNBALANCED';
}
