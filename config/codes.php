<?php

use App\Modules\Identity\Attachments\Models\Attachment;
use App\Modules\Identity\Users\Models\Person;

return [

    /*
    |--------------------------------------------------------------------------
    | QR codes & barcodes (Identity › Codes)
    |--------------------------------------------------------------------------
    | Printed payload: `<alias>:<code>.<signature>` — no table; every GenericEntity already has a unique code.
    | `summary`: attributes returned to staff scanners; `public`: attributes shown by the public verify page
    | (null = not publicly verifiable). Modules add theirs (appointment, invoice, payslip, clinical_order …).
    */
    'entities' => [
        'person' => [
            'model' => Person::class,
            'resource' => 'people',
            'summary' => ['code', 'full_name', 'date_of_birth', 'gender'],
            'public' => null,
        ],
        'attachment' => [
            'model' => Attachment::class,
            'resource' => 'attachments',
            'summary' => ['code', 'original_name', 'category', 'created_at'],
            'public' => ['code', 'category', 'created_at'],
        ],
    ],

    /* Signature length (hex chars of an HMAC-SHA256 keyed with APP_KEY). */
    'signature_length' => 12,

    'image_cache_hours' => 24,

];
