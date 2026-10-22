<?php

declare(strict_types=1);

namespace App\Modules\Payments\Catalog\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * This service code is already used.
 */
final class ServiceCodeTakenException extends ConflictException
{
    protected string $errorCode = 'SERVICE_CODE_TAKEN';
}
