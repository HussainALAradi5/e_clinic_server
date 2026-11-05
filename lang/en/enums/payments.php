<?php

// Enum labels (enums.<EnumName>.<value>) — Payments & Billing.

return [
    'PatientPackageStatus' => ['awaiting_payment' => 'Awaiting payment', 'active' => 'Active', 'used_up' => 'Used up', 'expired' => 'Expired', 'cancelled' => 'Cancelled'],
    'OnlinePaymentStatus' => ['initiated' => 'Waiting for payment', 'captured' => 'Paid', 'failed' => 'Failed', 'expired' => 'Expired'],
    'AppointmentFeeType' => ['no_show' => 'Missed appointment', 'late_cancel' => 'Late cancellation'],
    'AppointmentFeeStatus' => ['charged' => 'Charged', 'waived' => 'Waived'],
    'WalletEntryType' => ['deposit' => 'Deposit', 'invoice_payment' => 'Invoice paid', 'payment_reversal' => 'Payment returned', 'withdrawal' => 'Paid back', 'adjustment_credit' => 'Credit', 'adjustment_debit' => 'Debit', 'fee' => 'Fee'],
    'InvoicePaymentState' => ['draft' => 'Not issued', 'unpaid' => 'Unpaid', 'overdue' => 'Overdue', 'partially_paid' => 'Partly paid', 'paid' => 'Paid', 'refunded' => 'Refunded', 'cancelled' => 'Cancelled'],
];
