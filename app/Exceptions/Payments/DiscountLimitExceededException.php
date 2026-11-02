<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\ForbiddenActionException;

/**
 * A line discount exceeds the user's discount limit (config payments.discount_limits).
 */
final class DiscountLimitExceededException extends ForbiddenActionException
{
    protected string $errorCode = 'DISCOUNT_LIMIT_EXCEEDED';
}
