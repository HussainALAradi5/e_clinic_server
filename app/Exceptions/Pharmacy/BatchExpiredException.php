<?php

declare(strict_types=1);

namespace App\Exceptions\Pharmacy;

use App\Exceptions\Shared\ConflictException;

/**
 * The chosen batch is expired, quarantined or of another medicine.
 */
final class BatchExpiredException extends ConflictException
{
    protected string $errorCode = 'BATCH_EXPIRED';
}
