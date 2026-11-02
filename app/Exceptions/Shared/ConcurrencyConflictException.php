<?php

declare(strict_types=1);

namespace App\Exceptions\Shared;

/**
 * Record changed since it was read (version mismatch) or a lock could not be acquired.
 */
final class ConcurrencyConflictException extends ConflictException
{
    protected string $errorCode = 'CONCURRENT_MODIFICATION';
}
