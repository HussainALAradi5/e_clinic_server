<?php

declare(strict_types=1);

namespace App\Modules\Transactions\CashSessions\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The cashier already has an open cash session.
 */
final class CashSessionAlreadyOpenException extends ConflictException
{
    protected string $errorCode = 'CASH_SESSION_ALREADY_OPEN';
}
