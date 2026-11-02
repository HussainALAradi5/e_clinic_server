<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\ConflictException;

/**
 * Only same-day payments without refunds, in an open cash session, can be voided.
 */
final class PaymentNotVoidableException extends ConflictException
{
    protected string $errorCode = 'PAYMENT_NOT_VOIDABLE';
}
