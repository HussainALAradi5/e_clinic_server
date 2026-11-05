<?php

// Enum labels (enums.<EnumName>.<value>) — Transactions.

return [
    'TransactionType' => ['payment_in' => 'Payment received', 'refund_out' => 'Refund paid out', 'salary_out' => 'Salaries paid', 'expense_out' => 'Expense paid', 'bank_deposit' => 'Bank deposit', 'cash_adjustment' => 'Cash adjustment'],
    'TransactionDirection' => ['in' => 'Money in', 'out' => 'Money out', 'internal' => 'Transfer'],
];
