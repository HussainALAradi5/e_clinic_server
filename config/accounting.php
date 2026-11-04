<?php

use App\Enums\Accounting\AccountMappingKey;

return [

    /*
    |--------------------------------------------------------------------------
    | Accounting
    |--------------------------------------------------------------------------
    */

    /*
    | Tables that reference an account: a used account cannot be deleted or retyped (archive it instead).
    | Modules add theirs (journal_entry_lines.account_id, expense categories …).
    */
    'account_usages' => [
        'billable_services' => ['table' => 'billable_services', 'column' => 'revenue_account_id'],
        'journal_lines' => ['table' => 'journal_entry_lines', 'column' => 'account_id'],
        'invoice_items' => ['table' => 'invoice_items', 'column' => 'revenue_account_id'],
        'expenses' => ['table' => 'expenses', 'column' => 'expense_account_id'],
    ],

    /*
    | Manual entries above this amount (BHD) must be posted by someone other than their author (BR-06-JOUR-04).
    */
    'self_post_limit' => '1000.000',

    /*
    | Longest date range of the period reports (general ledger, income statement, cash flow, revenue).
    */
    'report_max_months' => 24,

    /*
    | Account receiving money, per payment method (payments, refunds, expenses). Card and BenefitPay settle through
    | card clearing; insurance approvals move the share to insurance receivable.
    */
    'payment_method_accounts' => [
        'cash' => AccountMappingKey::CashOnHand,
        'card' => AccountMappingKey::CardClearing,
        'benefit_pay' => AccountMappingKey::CardClearing,
        'bank_transfer' => AccountMappingKey::Bank,
        'cheque' => AccountMappingKey::Bank,
        'insurance' => AccountMappingKey::InsuranceReceivable,
        'patient_balance' => AccountMappingKey::PatientDeposits,
    ],

    /*
    | Default expense account (code from the chart) per expense category; the submitter may pick another one.
    */
    'expense_category_accounts' => [
        'rent' => '5300',
        'utilities' => '5300',
        'medical_supplies' => '5400',
        // Medicines bought for stock are an asset until dispensed (cost of goods sold is posted then).
        'pharmacy_purchase' => '1400',
        'office_supplies' => '5500',
        'maintenance' => '5500',
        'marketing' => '5600',
        'insurance' => '5600',
        'government_fees' => '5600',
        'professional_fees' => '5600',
        'other' => '5600',
    ],

    /*
    | Default clinic chart seeded by ChartOfAccountsSeeder: [code, name, type, parent code, group?, mapping key].
    | System accounts (is_system) can be renamed but never deleted.
    */
    'default_chart' => [
        ['1000', 'Assets', 'asset', null, true, null],
        ['1100', 'Cash and bank', 'asset', '1000', true, null],
        ['1110', 'Cash on hand', 'asset', '1100', false, AccountMappingKey::CashOnHand],
        ['1120', 'Bank', 'asset', '1100', false, AccountMappingKey::Bank],
        ['1130', 'Card clearing', 'asset', '1100', false, AccountMappingKey::CardClearing],
        ['1200', 'Receivables', 'asset', '1000', true, null],
        ['1210', 'Accounts receivable — patients', 'asset', '1200', false, AccountMappingKey::AccountsReceivable],
        ['1220', 'Insurance receivable', 'asset', '1200', false, AccountMappingKey::InsuranceReceivable],
        ['1300', 'Input VAT', 'asset', '1000', false, AccountMappingKey::InputVat],
        ['1400', 'Pharmacy inventory', 'asset', '1000', false, AccountMappingKey::PharmacyInventory],
        ['2000', 'Liabilities', 'liability', null, true, null],
        ['2100', 'VAT payable', 'liability', '2000', false, AccountMappingKey::VatPayable],
        ['2200', 'Salaries payable', 'liability', '2000', false, AccountMappingKey::SalariesPayable],
        ['2300', 'Social insurance payable', 'liability', '2000', false, AccountMappingKey::SocialInsurancePayable],
        ['2400', 'Payroll deductions payable', 'liability', '2000', false, null],
        ['2500', 'Patient deposits', 'liability', '2000', false, AccountMappingKey::PatientDeposits],
        ['3000', 'Equity', 'equity', null, true, null],
        ['3100', 'Owner capital', 'equity', '3000', false, null],
        ['3200', 'Retained earnings', 'equity', '3000', false, AccountMappingKey::RetainedEarnings],
        ['4000', 'Revenue', 'revenue', null, true, null],
        ['4100', 'Consultation revenue', 'revenue', '4000', false, null],
        ['4200', 'Procedure revenue', 'revenue', '4000', false, null],
        ['4300', 'Laboratory revenue', 'revenue', '4000', false, null],
        ['4400', 'Radiology revenue', 'revenue', '4000', false, null],
        ['4500', 'Pharmacy sales', 'revenue', '4000', false, null],
        ['4600', 'Fees revenue', 'revenue', '4000', false, AccountMappingKey::FeeRevenue],
        ['4900', 'Sales returns', 'revenue', '4000', false, AccountMappingKey::SalesReturns],
        ['5000', 'Expenses', 'expense', null, true, null],
        ['5100', 'Salaries expense', 'expense', '5000', false, AccountMappingKey::SalariesExpense],
        ['5200', 'Cost of goods sold', 'expense', '5000', false, AccountMappingKey::CostOfGoodsSold],
        ['5300', 'Rent and utilities', 'expense', '5000', false, null],
        ['5400', 'Medical supplies', 'expense', '5000', false, null],
        ['5500', 'Office and maintenance', 'expense', '5000', false, null],
        ['5600', 'General and administrative', 'expense', '5000', false, null],
        ['5900', 'Cash over / short', 'expense', '5000', false, AccountMappingKey::CashOverShort],
    ],

];
