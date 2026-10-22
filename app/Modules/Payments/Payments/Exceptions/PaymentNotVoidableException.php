<?php

declare(strict_types=1);

namespace App\Modules\Payments\Payments\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * Only same-day payments without refunds, in an open cash session, can be voided.
 */
final class PaymentNotVoidableException extends ConflictException
{
    protected string $errorCode = 'PAYMENT_NOT_VOIDABLE';
}
