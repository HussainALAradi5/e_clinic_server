<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\ConflictException;

/**
 * The fee is on an issued invoice: it is part of the patient's bill now, so it is refunded, not waived.
 */
final class FeeNotWaivableException extends ConflictException
{
    protected string $errorCode = 'FEE_NOT_WAIVABLE';
}
