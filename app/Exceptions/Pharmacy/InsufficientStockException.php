<?php

declare(strict_types=1);

namespace App\Exceptions\Pharmacy;

use App\Exceptions\Shared\ConflictException;

/**
 * Not enough dispensable stock (not expired, not quarantined) — `meta` names the medicine and what is available.
 */
final class InsufficientStockException extends ConflictException
{
    protected string $errorCode = 'INSUFFICIENT_STOCK';
}
