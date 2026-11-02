<?php

declare(strict_types=1);

namespace App\Exceptions\Transactions;

use App\Exceptions\Shared\ConflictException;

/**
 * Only draft expenses can be edited.
 */
final class ExpenseNotEditableException extends ConflictException
{
    protected string $errorCode = 'EXPENSE_NOT_EDITABLE';
}
