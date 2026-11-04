<?php

/*
| Journal narratives and report labels.
*/

return [
    'closing_entry' => 'Year-end closing :year',
    'reversal_of' => 'Reversal of :number — :reason',
    'invoice_issued' => 'Invoice :number — :patient',
    'invoice_cancelled' => 'Invoice :number cancelled',
    'payment_received' => 'Payment :code — invoice :invoice',
    'wallet_deposit' => 'Patient balance deposit :code',
    'wallet_withdrawal' => 'Patient balance paid back :code',
    'wallet_adjustment_credit' => 'Patient balance credit :code',
    'wallet_adjustment_debit' => 'Patient balance debit :code',
    'wallet_fee' => 'Fee taken from patient balance :code',
    'refund_processed' => 'Refund :code — payment :payment',
    'insurance_remittance' => 'Insurance remittance :reference — :count claim(s)',
    'expense_paid' => 'Expense :code — :payee',
    'payroll_accrual' => 'Salaries :month (:code)',
    'payroll_payment' => 'Salaries paid :month (:code)',
    'cash_over_short' => 'Cash drawer :code over / short',
    'cash_deposit' => 'Cash drawer :code deposited — :reference',
    'claim_remittance' => 'Insurance remittance :reference — :insurer',
    'medicines_dispensed' => 'Cost of medicines :code — :patient',
    'stock_adjusted' => 'Stock correction :lot — :reason',
    'stock_written_off' => 'Expired stock written off :lot',
    'stock_purchase' => 'Stock receipt :lot — :medication × :quantity',

    'reports' => [
        'trial_balance' => 'Trial balance',
        'general_ledger' => 'General ledger — :account',
        'income_statement' => 'Income statement',
        'balance_sheet' => 'Balance sheet',
        'cash_flow' => 'Cash flow',
        'revenue_by_doctor' => 'Revenue by doctor',
        'revenue_by_service' => 'Revenue by service',
        'receivables_aging' => 'Receivables aging',
        'as_of' => 'As of :date',
        'total' => 'Total',
        'revenue' => 'Revenue',
        'expenses' => 'Expenses',
        'total_revenue' => 'Total revenue',
        'total_expenses' => 'Total expenses',
        'net_profit' => 'Net profit',
        'total_assets' => 'Total assets',
        'total_liabilities_equity' => 'Total liabilities and equity',
        'current_earnings' => 'Current earnings (not closed yet)',
        'other_items' => 'Other items',
    ],
];
