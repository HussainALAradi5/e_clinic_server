<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\ConflictException;

/**
 * The clinic has not switched online payments on (admin settings): pay at the desk instead.
 */
final class OnlinePaymentsDisabledException extends ConflictException
{
    protected string $errorCode = 'ONLINE_PAYMENTS_DISABLED';
}
