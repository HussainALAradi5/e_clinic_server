<?php

declare(strict_types=1);

namespace App\Modules\Transactions\Expenses\Events;

use App\Modules\Transactions\Expenses\Models\Expense;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Submitted, approved, rejected or paid (the expense's status / paid_at tell which).
 */
final class ExpenseStatusChanged
{
    use Dispatchable;

    public function __construct(public readonly Expense $expense) {}
}
