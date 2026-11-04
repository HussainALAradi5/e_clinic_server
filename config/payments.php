<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payments & Billing
    |--------------------------------------------------------------------------
    */

    /*
    | Tables that reference a price-list item: a used service cannot be deleted (archive it instead).
    | Modules add theirs (invoice_items.billable_service_id, clinical_orders …).
    */
    'service_usages' => [
        'doctors' => ['table' => 'doctors', 'columns' => ['consultation_service_id', 'follow_up_service_id']],
        'appointments' => ['table' => 'appointments', 'columns' => ['billable_service_id']],
        'invoice_items' => ['table' => 'invoice_items', 'columns' => ['billable_service_id']],
        'medications' => ['table' => 'medications', 'columns' => ['billable_service_id']],
    ],

    /*
    | Largest line discount (percent of the line) per role — the best of the user's roles applies (BR-04-INVO-04).
    */
    'discount_limits' => [
        'super_admin' => 100,
        'admin' => 100,
        'accountant' => 100,
        'receptionist' => 10,
        'cashier' => 10,
    ],

    /*
    | Revenue account (chart code) of manual free-text invoice lines without a price-list service.
    */
    'free_line_revenue_account' => '4200',

    /*
    | Days a patient has to settle an issued invoice (0 = pay at the desk).
    */
    'payment_terms_days' => 0,

    /*
    | Expense approval limit per role in BHD (null = unlimited); the best of the approver's roles applies.
    */
    'expense_approval_limits' => [
        'super_admin' => null,
        'admin' => null,
        'accountant' => '500.000',
    ],

    /*
    | Cash drawer: largest |counted − expected| (BHD) closed without a supervisor.
    */
    'cash_tolerance' => '1.000',

    /*
    | Patient balance (money the clinic holds for a patient): limits of the WalletService.
    | - min_deposit / max_balance: smallest top-up and the largest balance one patient may hold (BHD).
    | - deposit_methods: how money comes in at the desk; withdraw_methods: how it is paid back.
    | - reason_min_length: withdrawals and adjustments need a written reason at least this long.
    | - adjustment_limits: largest single adjustment per role (null = unlimited; roles not listed may not adjust).
    */
    'wallet' => [
        'min_deposit' => '1.000',
        'max_balance' => '1000.000',
        'deposit_methods' => ['cash', 'card', 'benefit_pay', 'bank_transfer'],
        'withdraw_methods' => ['cash', 'bank_transfer'],
        'reason_min_length' => 10,
        'adjustment_limits' => [
            'super_admin' => null,
            'admin' => null,
            'accountant' => '100.000',
        ],
    ],

    /*
    | Fees for visits that did not happen (AppointmentFeeService):
    | - services: the price-list item of each fee — its price is the amount (admins change it in the price list;
    |   archive it or set 0 to stop charging that fee), posted to Fees revenue (4600).
    | - late_cancel_hours: a cancellation by the patient this close to the start is charged. Cancellations by the
    |   clinic, the doctor or the system never are.
    | - waive_reason_min: shortest reason for waiving a fee (admins and accountants only, while still on a draft).
    */
    'fees' => [
        'services' => [
            'no_show' => ['code' => 'FEE-NO-SHOW', 'name' => 'Missed appointment fee', 'price' => '5.000'],
            'late_cancel' => ['code' => 'FEE-LATE-CANCEL', 'name' => 'Late cancellation fee', 'price' => '3.000'],
        ],
        'late_cancel_hours' => 24,
        'waive_reason_min' => 10,
    ],

];
