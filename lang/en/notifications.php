<?php

/*
| Notification templates — minimum PHI (no diagnoses; "your result is ready" + portal link).
| One file per module in notifications/.
*/

return [
    ...require __DIR__.'/notifications/identity.php',
    ...require __DIR__.'/notifications/appointments.php',
    ...require __DIR__.'/notifications/payments.php',
    ...require __DIR__.'/notifications/transactions.php',
    ...require __DIR__.'/notifications/accounting.php',
    ...require __DIR__.'/notifications/pharmacy.php',
    ...require __DIR__.'/notifications/hr.php',
    ...require __DIR__.'/notifications/communication.php',
];
