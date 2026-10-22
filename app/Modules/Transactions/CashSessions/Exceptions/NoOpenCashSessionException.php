<?php

declare(strict_types=1);

namespace App\Modules\Transactions\CashSessions\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * Cash payments, refunds and expenses need the user's open cash session.
 */
final class NoOpenCashSessionException extends ConflictException
{
    protected string $errorCode = 'NO_OPEN_CASH_SESSION';
}
