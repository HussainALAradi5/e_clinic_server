<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\ConflictException;

/**
 * The visit / order / dispensation already has a line on a non-cancelled invoice.
 */
final class AlreadyChargedException extends ConflictException
{
    protected string $errorCode = 'ALREADY_CHARGED';
}
