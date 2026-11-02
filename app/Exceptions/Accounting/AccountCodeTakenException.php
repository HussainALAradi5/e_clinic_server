<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

use App\Exceptions\Shared\ConflictException;

/**
 * The account code is already used.
 */
final class AccountCodeTakenException extends ConflictException
{
    protected string $errorCode = 'ACCOUNT_CODE_TAKEN';
}
