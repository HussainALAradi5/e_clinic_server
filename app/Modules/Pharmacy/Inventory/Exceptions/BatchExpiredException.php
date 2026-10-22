<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Inventory\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The chosen batch is expired, quarantined or of another medicine.
 */
final class BatchExpiredException extends ConflictException
{
    protected string $errorCode = 'BATCH_EXPIRED';
}
