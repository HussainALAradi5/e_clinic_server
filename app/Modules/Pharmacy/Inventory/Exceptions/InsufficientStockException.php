<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Inventory\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * Not enough dispensable stock (not expired, not quarantined) — `meta` names the medicine and what is available.
 */
final class InsufficientStockException extends ConflictException
{
    protected string $errorCode = 'INSUFFICIENT_STOCK';
}
