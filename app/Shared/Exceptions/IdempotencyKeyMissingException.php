<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * A money-moving endpoint was called without an Idempotency-Key header.
 */
final class IdempotencyKeyMissingException extends DomainException
{
    protected int $status = 428;

    protected string $errorCode = 'IDEMPOTENCY_KEY_REQUIRED';
}
