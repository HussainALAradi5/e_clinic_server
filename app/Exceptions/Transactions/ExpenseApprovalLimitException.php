<?php

declare(strict_types=1);

namespace App\Exceptions\Transactions;

use App\Exceptions\Shared\ForbiddenActionException;

/**
 * The amount exceeds the approver's limit (config payments.expense_approval_limits).
 */
final class ExpenseApprovalLimitException extends ForbiddenActionException
{
    protected string $errorCode = 'EXPENSE_APPROVAL_LIMIT';
}
