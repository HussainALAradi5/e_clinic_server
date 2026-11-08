<?php

/*
| Enum labels: enums.<EnumName>.<value>. Missing keys fall back to the value in sentence case (`checked_in` → `Checked in`);
| list here only the words that need care (abbreviations, hyphens, clinic wording).
| One file per module in enums/, by the folder of the enum class.
*/

return [
    ...require __DIR__.'/enums/shared.php',
    ...require __DIR__.'/enums/identity.php',
    ...require __DIR__.'/enums/appointments.php',
    ...require __DIR__.'/enums/payments.php',
    ...require __DIR__.'/enums/transactions.php',
    ...require __DIR__.'/enums/accounting.php',
    ...require __DIR__.'/enums/pharmacy.php',
    ...require __DIR__.'/enums/hr.php',
    ...require __DIR__.'/enums/dashboards.php',
    ...require __DIR__.'/enums/communication.php',
    ...require __DIR__.'/enums/reference.php',
    ...require __DIR__.'/enums/documents.php',
];
