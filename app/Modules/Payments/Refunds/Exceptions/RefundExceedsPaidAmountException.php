<?php

declare(strict_types=1);

namespace App\Modules\Payments\Refunds\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * Refunds of the payment would exceed the amount paid.
 */
final class RefundExceedsPaidAmountException extends BusinessValidationException
{
    protected string $errorCode = 'REFUND_EXCEEDS_PAID';
}
