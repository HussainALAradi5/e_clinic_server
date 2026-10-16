<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * No listable resource with this name, or the user cannot read it.
 */
final class UnknownResourceException extends DomainException
{
    protected int $status = 404;

    protected string $errorCode = 'UNKNOWN_RESOURCE';
}
