<?php

declare(strict_types=1);

namespace App\Modules\Payments\Invoices\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The visit / order / dispensation already has a line on a non-cancelled invoice.
 */
final class AlreadyChargedException extends ConflictException
{
    protected string $errorCode = 'ALREADY_CHARGED';
}
