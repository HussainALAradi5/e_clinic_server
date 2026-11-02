<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\ConflictException;

/**
 * This service is still used. Archive it instead of deleting it.
 */
final class ServiceInUseException extends ConflictException
{
    protected string $errorCode = 'SERVICE_IN_USE';
}
