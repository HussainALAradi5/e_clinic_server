<?php

// PDF document wording (documents.<document>.<text>) — Letterhead and shared parts.

return [
    'letterhead' => [
        'eyebrow' => ':clinic document',
        'export' => ':clinic · export',
        'printed' => 'Printed :at',
        'printed_by' => 'by :name',
        'issued_by' => 'Issued by',
        'page' => 'Page {PAGE_NUM} of {PAGE_COUNT}',
        'exported' => 'Exported :at from :clinic',
        'verification' => 'Verification code',
    ],
    'common' => [
        'patient' => 'Patient',
        'name' => 'Name',
        'cpr' => 'CPR / national ID',
        'not_recorded' => 'Not recorded',
        'file_no' => 'File no.',
        'born' => 'Born',
        'age' => ':date (:age y)',
        'sex' => 'Sex',
        'phone' => 'Phone',
        'notes' => 'Notes',
        'signature' => 'Signature',
        'stamp' => 'Clinic stamp',
        'cancelled_on' => 'Cancelled on :at',
        'days' => '{1} :count day|[2,*] :count days',
    ],
    'templates' => [
        'copy_of' => ':name (copy)',
    ],
    'written' => [
        'issued' => 'Issued',
        'visit' => 'Visit',
        'kind' => 'Document',
        'author' => 'Written by',
        'verify' => 'Scan the code to check this document with the clinic.',
    ],
];
