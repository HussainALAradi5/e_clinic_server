<?php

// Notification wording (notifications.<group>.<event>.<part>) — Pharmacy.

return [
    'prescription' => [
        'created' => [
            'subject' => 'Prescription :code is ready at the pharmacy',
            'body' => 'Dr :doctor wrote you prescription :code. Show it at the clinic pharmacy before :valid_until.',
        ],
    ],
    'pharmacy' => [
        'stock_alert' => [
            'subject' => 'Pharmacy stock needs attention',
            'body' => ':low medicine(s) at or below their reorder level and :expiring lot(s) expiring within :days days.',
        ],
    ],
];
