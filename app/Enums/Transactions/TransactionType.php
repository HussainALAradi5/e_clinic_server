<?php

declare(strict_types=1);

namespace App\Enums\Transactions;

use App\Shared\Contracts\HasLabel;
use App\Shared\Traits\EnumHelpers;

/**
 * Type of money movement in the v_money_movements view (derived from the source document).
 */
enum TransactionType: string implements HasLabel
{
    use EnumHelpers;

    case PaymentIn = 'payment_in';
    case RefundOut = 'refund_out';
    case SalaryOut = 'salary_out';
    case ExpenseOut = 'expense_out';
    case BankDeposit = 'bank_deposit';
    case CashAdjustment = 'cash_adjustment';
}
