<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * The payment is greater than the balance due.
 */
final class OverpaymentException extends BusinessValidationException
{
    protected string $errorCode = 'PAYMENT_EXCEEDS_BALANCE';
}
