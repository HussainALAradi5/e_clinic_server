<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\ConflictException;

/**
 * The patient's balance does not cover the amount (a payment, a pay-back or a debit adjustment).
 */
final class InsufficientBalanceException extends ConflictException
{
    protected string $errorCode = 'WALLET_INSUFFICIENT';
}
