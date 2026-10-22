<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Journal\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * Only draft manual entries can be edited, posted or deleted.
 */
final class JournalEntryNotEditableException extends ConflictException
{
    protected string $errorCode = 'JOURNAL_NOT_EDITABLE';
}
