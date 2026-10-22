<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Accounts\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * The account is used by other records: it can be archived but not deleted or retyped.
 */
final class AccountHasTransactionsException extends ConflictException
{
    protected string $errorCode = 'ACCOUNT_HAS_TRANSACTIONS';
}
