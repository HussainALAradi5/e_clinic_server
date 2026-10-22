<?php

declare(strict_types=1);

namespace App\Modules\Payments\Payments\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * The payment is greater than the balance due.
 */
final class OverpaymentException extends BusinessValidationException
{
    protected string $errorCode = 'PAYMENT_EXCEEDS_BALANCE';
}
