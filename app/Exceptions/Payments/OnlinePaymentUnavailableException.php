<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\ConflictException;

/**
 * The payment gateway did not answer or refused the request; nothing was charged. Pay at the desk or try later.
 */
final class OnlinePaymentUnavailableException extends ConflictException
{
    protected string $errorCode = 'ONLINE_PAYMENT_UNAVAILABLE';
}
