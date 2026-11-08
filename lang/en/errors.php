<?php

/*
| Messages for stable error codes (the client switches on error_code, never on these texts).
| One file per module in errors/, by the module whose exception raises the code; shared codes in errors/shared.php.
*/

return [
    ...require __DIR__.'/errors/shared.php',
    ...require __DIR__.'/errors/identity.php',
    ...require __DIR__.'/errors/appointments.php',
    ...require __DIR__.'/errors/payments.php',
    ...require __DIR__.'/errors/transactions.php',
    ...require __DIR__.'/errors/accounting.php',
    ...require __DIR__.'/errors/pharmacy.php',
    ...require __DIR__.'/errors/hr.php',
    ...require __DIR__.'/errors/dashboards.php',
    ...require __DIR__.'/errors/communication.php',
    ...require __DIR__.'/errors/reference.php',
    ...require __DIR__.'/errors/documents.php',
    ...require __DIR__.'/errors/transfer.php',
];
