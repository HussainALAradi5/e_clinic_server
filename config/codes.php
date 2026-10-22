<?php

use App\Modules\Appointments\Appointments\Models\Appointment;
use App\Modules\Appointments\Orders\Models\ClinicalOrder;
use App\Modules\Appointments\Records\Models\PatientConsent;
use App\Modules\Identity\Attachments\Models\Attachment;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Payments\Invoices\Models\Invoice;
use App\Modules\Payments\Payments\Models\Payment;
use App\Modules\Pharmacy\Dispensing\Models\Dispensation;
use App\Modules\Pharmacy\Inventory\Models\MedicationBatch;
use App\Modules\Pharmacy\Prescriptions\Models\Prescription;
use App\Modules\Transactions\CashSessions\Models\CashSession;

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
        'appointment' => [
            'model' => Appointment::class,
            'resource' => 'appointments',
            // Shown by the patient (portal) and scanned at the front desk to check in.
            'summary' => ['code', 'type', 'scheduled_date', 'start_time', 'status'],
            'public' => null,
        ],
        'clinical_order' => [
            'model' => ClinicalOrder::class,
            'resource' => 'orders',
            'summary' => ['code', 'type', 'priority', 'status', 'body_part', 'created_at'],
            // Referral letters and result prints can be checked by the receiving hospital (no clinical content).
            'public' => ['code', 'type', 'status', 'created_at'],
        ],
        'patient_consent' => [
            'model' => PatientConsent::class,
            'resource' => 'consents',
            'summary' => ['code', 'type', 'signed_by_name', 'signed_at', 'valid_until', 'revoked_at'],
            'public' => ['code', 'type', 'signed_at', 'revoked_at'],
        ],
        'invoice' => [
            'model' => Invoice::class,
            'resource' => 'invoices',
            'summary' => ['code', 'invoice_number', 'issue_date', 'total', 'balance_due', 'status'],
            // Printed invoices can be checked by insurers and employers (amounts only, no lines or diagnoses).
            'public' => ['invoice_number', 'issue_date', 'total', 'status'],
        ],
        'payment' => [
            'model' => Payment::class,
            'resource' => 'payments',
            'summary' => ['code', 'amount', 'method', 'paid_at', 'status'],
            'public' => ['code', 'amount', 'paid_at', 'status'],
        ],
        'cash_session' => [
            'model' => CashSession::class,
            'resource' => 'cash_sessions',
            'summary' => ['code', 'status', 'opened_at', 'closed_at'],
            'public' => null,
        ],
        'prescription' => [
            'model' => Prescription::class,
            'resource' => 'prescriptions',
            'summary' => ['code', 'status', 'valid_until', 'created_at'],
            // Another pharmacy can check a printed prescription is genuine and still open (no medicines shown).
            'public' => ['code', 'status', 'valid_until', 'created_at'],
        ],
        'dispensation' => [
            'model' => Dispensation::class,
            'resource' => 'dispensing',
            'summary' => ['code', 'kind', 'status', 'dispensed_at'],
            'public' => null,
        ],
        'medication_batch' => [
            'model' => MedicationBatch::class,
            'resource' => 'inventory',
            'summary' => ['code', 'batch_number', 'expiry_date', 'qty_on_hand', 'is_quarantined'],
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
