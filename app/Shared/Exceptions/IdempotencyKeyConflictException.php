<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * The same Idempotency-Key was reused with a different request body.
 */
final class IdempotencyKeyConflictException extends ConflictException
{
    protected string $errorCode = 'IDEMPOTENCY_KEY_REUSED';
}
