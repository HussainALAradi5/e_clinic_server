<?php

use App\Models\Appointments\Appointment;
use App\Models\Appointments\ClinicalOrder;
use App\Models\Appointments\PatientConsent;
use App\Models\Appointments\SickLeave;
use App\Models\Documents\WrittenDocument;
use App\Models\Identity\Attachment;
use App\Models\Identity\Person;
use App\Models\Payments\Invoice;
use App\Models\Payments\Payment;
use App\Models\Pharmacy\Dispensation;
use App\Models\Pharmacy\MedicationBatch;
use App\Models\Pharmacy\Prescription;
use App\Models\Transactions\CashSession;

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
        'sick_leave' => [
            'model' => SickLeave::class,
            'resource' => 'sick_leaves',
            'summary' => ['code', 'start_date', 'end_date', 'days', 'issued_at', 'cancelled_at'],
            // Employers check a certificate: its dates and whether it was cancelled — never the reason.
            'public' => ['code', 'start_date', 'end_date', 'days', 'issued_at', 'cancelled_at'],
        ],
        'written_document' => [
            'model' => WrittenDocument::class,
            'resource' => 'written_documents',
            'summary' => ['code', 'kind', 'title', 'issued_at'],
            'public' => ['code', 'kind', 'issued_at'],
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
