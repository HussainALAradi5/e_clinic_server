<?php

// Notification wording (notifications.<group>.<event>.<part>) — Payments & Billing.

return [
    'payment' => [
        'received' => [
            'subject' => 'Payment received: :amount',
            'body' => 'Thank you — we received :amount (:method) for invoice :invoice. Left to pay: :balance. Your receipt is in My invoices.',
        ],
        'reminder' => [
            'subject' => 'Reminder: :amount to pay',
            'body' => 'Invoice :invoice of :date has :amount left for you to pay. Pay online in My invoices or at the clinic desk.',
        ],
    ],
    'refund' => [
        'requested' => [
            'subject' => 'Refund to approve: :amount',
            'body' => 'Refund :code of :amount for :patient is waiting for your approval.',
        ],
        'approved' => [
            'subject' => 'Refund approved: :amount',
            'body' => 'Refund :code of :amount for :patient is approved and can be paid out.',
        ],
        'rejected' => [
            'subject' => 'Refund rejected: :code',
            'body' => 'Refund :code of :amount for :patient was rejected: :reason',
        ],
    ],
];
