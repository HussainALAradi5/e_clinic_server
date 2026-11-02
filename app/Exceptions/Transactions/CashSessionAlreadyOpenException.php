<?php

declare(strict_types=1);

namespace App\Exceptions\Transactions;

use App\Exceptions\Shared\ConflictException;

/**
 * The cashier already has an open cash session.
 */
final class CashSessionAlreadyOpenException extends ConflictException
{
    protected string $errorCode = 'CASH_SESSION_ALREADY_OPEN';
}
