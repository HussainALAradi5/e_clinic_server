<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Generated documents (PDF)
    |--------------------------------------------------------------------------
    | Letterhead of every PDF the server makes (sick leaves, results, invoices, list exports, reports) and of the
    | Excel exports. Colours follow the e-Clinic brand mark.
    */

    'clinic' => [
        'name' => env('DOCUMENT_CLINIC_NAME', 'e-Clinic'),
        'tagline' => env('DOCUMENT_CLINIC_TAGLINE', 'Family medicine · Imaging · Laboratory · Pharmacy'),
        'address' => env('DOCUMENT_CLINIC_ADDRESS', 'Building 1024, Road 3618, Block 436, Seef, Kingdom of Bahrain'),
        'phone' => env('DOCUMENT_CLINIC_PHONE', '+973 1700 0000'),
        'email' => env('DOCUMENT_CLINIC_EMAIL', 'care@e-clinic.example'),
        'website' => env('DOCUMENT_CLINIC_WEBSITE', 'e-clinic-web.onrender.com'),
    ],

    'colors' => [
        'brand' => '#0b5d57',
        'brand_dark' => '#073c39',
        'brand_soft' => '#e6f4f1',
        'accent' => '#f2b544',
        'ink' => '#0f172a',
        'muted' => '#64748b',
        'line' => '#e2e8f0',
        'stripe' => '#f8fafc',
    ],

    /* List exports wider than this many columns are printed on landscape paper. */
    'landscape_after_columns' => 6,

    /* Pixel size of the verification QR drawn on documents. */
    'qr_pixels' => 240,

    /* Pixel width of the reference barcode (Code 128) beside the QR. */
    'barcode_pixels' => 560,
];
