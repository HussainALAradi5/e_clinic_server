<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\ConflictException;

/**
 * The fee was waived before (a second click, or two people at once).
 */
final class FeeAlreadyWaivedException extends ConflictException
{
    protected string $errorCode = 'FEE_ALREADY_WAIVED';
}
