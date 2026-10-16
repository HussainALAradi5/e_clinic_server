<?php

declare(strict_types=1);

namespace App\Modules\Reference\Addresses\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * The address does not satisfy its country's format (Bahrain: building, road and block).
 */
final class InvalidAddressException extends BusinessValidationException
{
    protected string $errorCode = 'ADDRESS_INVALID';
}
