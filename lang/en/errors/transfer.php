<?php

// Error messages by error code — Export & import.

return [
    'TRANSFER_NOT_ALLOWED' => 'You are not allowed to export or import this list.',
    'EXPORT_TOO_LARGE' => 'PDF exports are limited to :max rows. Narrow the filters or export to Excel.',
    'IMPORT_FILE_INVALID' => 'The file could not be read. Upload the Excel template (.xlsx) filled in.',
    'IMPORT_MISSING_COLUMNS' => 'The file is missing required columns: :columns. Download the template to see them.',
    'IMPORT_EMPTY' => 'The file has no rows to import.',
    'IMPORT_TOO_MANY_ROWS' => 'Import at most :max rows at a time.',
    'IMPORT_FAILED' => '{1} 1 row has problems; nothing was imported.|[2,*] :count rows have problems; nothing was imported.',
];
