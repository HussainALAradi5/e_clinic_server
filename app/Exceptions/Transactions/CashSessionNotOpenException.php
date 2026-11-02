<?php

declare(strict_types=1);

namespace App\Exceptions\Transactions;

use App\Exceptions\Shared\ConflictException;

/**
 * The cash session is not open.
 */
final class CashSessionNotOpenException extends ConflictException
{
    protected string $errorCode = 'CASH_SESSION_NOT_OPEN';
}
