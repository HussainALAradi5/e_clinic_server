<?php

declare(strict_types=1);

namespace App\Modules\Transactions\CashSessions\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The cash session is not open.
 */
final class CashSessionNotOpenException extends ConflictException
{
    protected string $errorCode = 'CASH_SESSION_NOT_OPEN';
}
