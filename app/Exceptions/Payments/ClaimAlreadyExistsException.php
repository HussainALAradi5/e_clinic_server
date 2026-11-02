<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\ConflictException;

/**
 * The invoice already has an open or approved claim.
 */
final class ClaimAlreadyExistsException extends ConflictException
{
    protected string $errorCode = 'CLAIM_ALREADY_EXISTS';
}
