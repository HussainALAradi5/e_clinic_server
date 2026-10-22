<?php

declare(strict_types=1);

namespace App\Modules\HR\Leaves\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * Requested days exceed the remaining balance — `meta` has what is left.
 */
final class InsufficientLeaveBalanceException extends BusinessValidationException
{
    protected string $errorCode = 'LEAVE_INSUFFICIENT_BALANCE';
}
