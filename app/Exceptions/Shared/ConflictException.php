<?php

declare(strict_types=1);

namespace App\Exceptions\Shared;

/**
 * Business conflict: state, duplicates, locks, closed period (409).
 */
abstract class ConflictException extends DomainException
{
    protected int $status = 409;

    protected string $errorCode = 'CONFLICT';
}
