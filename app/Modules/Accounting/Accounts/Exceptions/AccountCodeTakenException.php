<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Accounts\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The account code is already used.
 */
final class AccountCodeTakenException extends ConflictException
{
    protected string $errorCode = 'ACCOUNT_CODE_TAKEN';
}
