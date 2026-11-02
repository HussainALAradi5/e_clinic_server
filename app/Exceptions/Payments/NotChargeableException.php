<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * Billing by hand needs a visit that has started (or an order not cancelled) with a price-list service.
 */
final class NotChargeableException extends BusinessValidationException
{
    protected string $errorCode = 'NOT_CHARGEABLE';
}
