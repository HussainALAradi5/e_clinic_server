<?php

// Notification wording (notifications.<group>.<event>.<part>) — Transactions.

return [
    'expense' => [
        'submitted' => [
            'subject' => 'Expense to approve: :amount',
            'body' => 'Expense :code of :amount to :payee is waiting for your approval.',
        ],
        'approved' => [
            'subject' => 'Expense approved: :code',
            'body' => 'Your expense :code of :amount to :payee is approved and will be paid.',
        ],
        'rejected' => [
            'subject' => 'Expense rejected: :code',
            'body' => 'Your expense :code of :amount to :payee was rejected: :reason',
        ],
    ],
];
