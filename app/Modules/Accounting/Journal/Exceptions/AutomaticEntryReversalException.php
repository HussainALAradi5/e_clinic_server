<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Journal\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * Automatic entries are reversed through their source document (void payment, cancel invoice …).
 */
final class AutomaticEntryReversalException extends ConflictException
{
    protected string $errorCode = 'JOURNAL_AUTOMATIC_REVERSAL';
}
