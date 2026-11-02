<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

use App\Exceptions\Shared\ConflictException;

/**
 * The entry was already reversed.
 */
final class JournalEntryAlreadyReversedException extends ConflictException
{
    protected string $errorCode = 'JOURNAL_ALREADY_REVERSED';
}
