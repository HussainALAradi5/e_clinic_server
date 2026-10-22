<?php

declare(strict_types=1);

namespace App\Modules\Payments\Catalog\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * This service is still used. Archive it instead of deleting it.
 */
final class ServiceInUseException extends ConflictException
{
    protected string $errorCode = 'SERVICE_IN_USE';
}
