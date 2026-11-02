<?php

use App\Models\Appointments\Appointment;
use App\Models\Appointments\ClinicalOrder;
use App\Models\Appointments\Patient;
use App\Models\Appointments\PatientConsent;
use App\Models\Communication\Message;
use App\Models\Communication\SupportTicket;
use App\Models\HR\LeaveRequest;
use App\Models\Identity\Person;
use App\Models\Pharmacy\Medication;
use App\Models\Pharmacy\MedicationBatch;
use App\Models\Transactions\Expense;

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
        'patient' => ['model' => Patient::class, 'resource' => 'patients'],
        'appointment' => ['model' => Appointment::class, 'resource' => 'appointments'],
        'clinical_order' => ['model' => ClinicalOrder::class, 'resource' => 'orders'],
        'patient_consent' => ['model' => PatientConsent::class, 'resource' => 'consents'],
        'expense' => ['model' => Expense::class, 'resource' => 'expenses'],
        'medication' => ['model' => Medication::class, 'resource' => 'medications'],
        'medication_batch' => ['model' => MedicationBatch::class, 'resource' => 'inventory'],
        'leave_request' => ['model' => LeaveRequest::class, 'resource' => 'leaves'],
        'chat_message' => ['model' => Message::class, 'resource' => 'chat'],
        'support_ticket' => ['model' => SupportTicket::class, 'resource' => 'tickets'],
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
        // Staff chat: photos, short videos and documents sent in a conversation, and recorded voice notes.
        'chat_media' => ['mimes' => [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm', 'application/pdf', 'text/plain',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ], 'max_mb' => 20, 'sensitive' => false],
        'voice_note' => ['mimes' => ['audio/webm', 'audio/ogg', 'audio/mp4', 'audio/mpeg', 'audio/aac', 'audio/x-m4a', 'audio/wav', 'video/webm'], 'max_mb' => 10, 'sensitive' => false],
        // Screenshots, photos and PDFs on a support ticket (the requester's, and the support team's).
        'ticket_attachment' => ['mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], 'max_mb' => 10, 'sensitive' => false],
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
