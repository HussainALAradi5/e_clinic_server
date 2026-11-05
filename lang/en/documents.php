<?php

/*
|--------------------------------------------------------------------------
| Generated documents (PDF) — titles, headings, labels and notes
|--------------------------------------------------------------------------
| Wording of every document the server prints (resources/views/pdf, App\Services\Documents\Builders).
| One file per kind of document in documents/.
*/

return [
    ...require __DIR__.'/documents/common.php',
    ...require __DIR__.'/documents/clinical.php',
    ...require __DIR__.'/documents/billing.php',
    ...require __DIR__.'/documents/hr.php',
];
