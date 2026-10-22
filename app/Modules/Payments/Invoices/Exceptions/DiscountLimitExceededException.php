<?php

declare(strict_types=1);

namespace App\Modules\Payments\Invoices\Exceptions;

use App\Shared\Exceptions\ForbiddenActionException;

/**
 * A line discount exceeds the user's discount limit (config payments.discount_limits).
 */
final class DiscountLimitExceededException extends ForbiddenActionException
{
    protected string $errorCode = 'DISCOUNT_LIMIT_EXCEEDED';
}
