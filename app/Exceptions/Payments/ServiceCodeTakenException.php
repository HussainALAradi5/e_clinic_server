<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\ConflictException;

/**
 * This service code is already used.
 */
final class ServiceCodeTakenException extends ConflictException
{
    protected string $errorCode = 'SERVICE_CODE_TAKEN';
}
