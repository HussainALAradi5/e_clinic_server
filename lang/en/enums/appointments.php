<?php

// Enum labels (enums.<EnumName>.<value>) — Appointments & Clinical.

return [
    'AppointmentSource' => ['walk_in' => 'Walk-in', 'phone' => 'Phone', 'portal' => 'Online', 'referral' => 'Referral'],
    'AppointmentStatus' => ['checked_in' => 'Checked in', 'no_show' => 'No-show'],
    'VisitTiming' => ['coming' => 'Coming', 'today' => 'Today', 'past' => 'Past', 'missed' => 'Missed'],
    'AppointmentType' => ['follow_up' => 'Follow-up', 'teleconsultation' => 'Video consultation', 'treatment_session' => 'Treatment session'],
    'CancelledBy' => ['system' => 'Automatically'],
    'ClinicalOrderType' => ['xray' => 'X-ray', 'ultrasound' => 'Ultrasound', 'ct' => 'CT scan', 'mri' => 'MRI scan', 'lab_test' => 'Lab test', 'ecg' => 'ECG', 'referral' => 'Referral'],
    'RoomType' => ['physical' => 'Physiotherapy', 'xray' => 'X-ray', 'session_room' => 'Treatment sessions', 'dental' => 'Dental', 'procedure' => 'Procedure room', 'vaccination' => 'Vaccination'],
];
