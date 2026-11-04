<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * An online payment must be at least `payments.online.min_amount` and at most what the patient owes now (their
 * co-pay on an insured invoice).
 */
final class OnlinePaymentAmountException extends BusinessValidationException
{
    protected string $errorCode = 'ONLINE_PAYMENT_AMOUNT';
}
