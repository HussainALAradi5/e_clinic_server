<?php

use App\Modules\Identity\Users\Models\Person;

return [

    /*
    |--------------------------------------------------------------------------
    | Attachments (Identity › Attachments)
    |--------------------------------------------------------------------------
    */

    /*
    | Records that can own files: morph alias => model + the access resource whose rights apply to its files.
    | Modules add theirs (clinical_order, leave_request, expense, appointment …).
    */
    'owners' => [
        'person' => ['model' => Person::class, 'resource' => 'people'],
    ],

    /* Category => allowed MIME types (detected from content), max size and PHI flag. */
    'categories' => [
        'medical_certificate' => ['mimes' => ['application/pdf', 'image/jpeg', 'image/png'], 'max_mb' => 5, 'sensitive' => true],
        'xray_image' => ['mimes' => ['application/dicom', 'application/octet-stream', 'image/jpeg', 'image/png'], 'max_mb' => 50, 'sensitive' => true],
        'lab_result' => ['mimes' => ['application/pdf', 'image/jpeg', 'image/png'], 'max_mb' => 20, 'sensitive' => true],
        'clinical_document' => ['mimes' => ['application/pdf', 'image/jpeg', 'image/png'], 'max_mb' => 20, 'sensitive' => true],
        'consent_form' => ['mimes' => ['application/pdf', 'image/jpeg', 'image/png'], 'max_mb' => 10, 'sensitive' => true],
        'referral_letter' => ['mimes' => ['application/pdf', 'image/jpeg', 'image/png'], 'max_mb' => 10, 'sensitive' => true],
        'insurance_document' => ['mimes' => ['application/pdf', 'image/jpeg', 'image/png'], 'max_mb' => 10, 'sensitive' => false],
        'id_document' => ['mimes' => ['application/pdf', 'image/jpeg', 'image/png'], 'max_mb' => 5, 'sensitive' => false],
        'invoice_scan' => ['mimes' => ['application/pdf', 'image/jpeg', 'image/png'], 'max_mb' => 10, 'sensitive' => false],
        'payslip' => ['mimes' => ['application/pdf'], 'max_mb' => 5, 'sensitive' => false],
        'other' => ['mimes' => [
            'application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'text/plain', 'text/csv',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ], 'max_mb' => 20, 'sensitive' => false],
    ],

    /* Multipart upload limits (bigger files use the resumable flow). */
    'multipart' => ['max_files' => 20, 'max_file_mb' => 20, 'max_total_mb' => 100],

    /* Resumable uploads. */
    'resumable' => ['max_mb' => 500, 'chunk_bytes' => 5 * 1024 * 1024, 'expires_hours' => 24],

    /* ZIP download limits. */
    'zip' => ['max_files' => 200, 'max_mb' => 1024],

    /* Antivirus: `clamav` (clamdscan) or `none` (marks files clean — development only). */
    'scanner' => env('ATTACHMENTS_SCANNER', 'none'),

    'clamav_binary' => env('CLAMDSCAN_BINARY', 'clamdscan'),

];
