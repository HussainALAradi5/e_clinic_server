<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * The user may not perform this action (403).
 */
abstract class ForbiddenActionException extends DomainException
{
    protected int $status = 403;

    protected string $errorCode = 'FORBIDDEN';
}
