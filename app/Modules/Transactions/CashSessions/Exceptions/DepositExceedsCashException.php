<?php

declare(strict_types=1);

namespace App\Modules\Transactions\CashSessions\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * The deposit is more than the counted cash of the session.
 */
final class DepositExceedsCashException extends BusinessValidationException
{
    protected string $errorCode = 'DEPOSIT_EXCEEDS_CASH';
}
