<?php

declare(strict_types=1);

namespace App\Exceptions\Shared;

/**
 * Requested status change is not allowed from the current status.
 */
final class InvalidStateTransitionException extends ConflictException
{
    protected string $errorCode = 'INVALID_STATE_TRANSITION';
}
