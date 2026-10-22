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

];
