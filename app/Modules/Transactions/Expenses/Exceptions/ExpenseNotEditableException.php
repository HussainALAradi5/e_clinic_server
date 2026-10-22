<?php

declare(strict_types=1);

namespace App\Modules\Transactions\Expenses\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * Only draft expenses can be edited.
 */
final class ExpenseNotEditableException extends ConflictException
{
    protected string $errorCode = 'EXPENSE_NOT_EDITABLE';
}
