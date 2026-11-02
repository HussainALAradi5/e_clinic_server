<?php

declare(strict_types=1);

namespace App\Events\Transactions;

use App\Models\Transactions\Expense;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Submitted, approved, rejected or paid (the expense's status / paid_at tell which).
 */
final class ExpenseStatusChanged
{
    use Dispatchable;

    public function __construct(public readonly Expense $expense) {}
}
