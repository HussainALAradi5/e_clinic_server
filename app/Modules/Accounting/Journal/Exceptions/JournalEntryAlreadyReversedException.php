<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Journal\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The entry was already reversed.
 */
final class JournalEntryAlreadyReversedException extends ConflictException
{
    protected string $errorCode = 'JOURNAL_ALREADY_REVERSED';
}
