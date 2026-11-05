<?php

// Notification wording (notifications.<group>.<event>.<part>) — Appointments & Clinical.

return [
    'patient' => [
        'emergency_access' => [
            'subject' => 'Emergency access to patient :patient',
            'body' => ':clinician opened patient :patient outside their care. Reason: :reason',
        ],
    ],
    'appointment' => [
        'booked' => [
            'subject' => 'Appointment booked: :date at :time',
            'body' => 'Your appointment :code with :provider is booked for :date at :time.',
        ],
        'rescheduled' => [
            'subject' => 'Appointment moved to :date at :time',
            'body' => 'Your appointment with :provider has moved to :date at :time (reference :code).',
        ],
        'reminder' => [
            'subject' => 'Reminder: appointment :date at :time',
            'body' => 'Reminder: you see :provider on :date at :time. Please arrive 10 minutes early.',
        ],
        'cancelled' => [
            'subject' => 'Appointment cancelled',
            'body' => 'Your appointment :code on :date at :time has been cancelled. Book a new time in the patient portal or call the clinic.',
        ],
    ],
    'treatment' => [
        'urgent' => [
            'subject' => 'Urgent treatment to schedule: :code',
            'body' => ':patient needs the first session of ":title" (:code) within 24 hours. Book it from the treatment queue.',
        ],
        'attendance_alert' => [
            'subject' => ':patient missed :count sessions in a row',
            'body' => ':patient missed :count sessions of ":title" (:code) in a row. Call the patient and rebook.',
        ],
        'completed' => [
            'subject' => 'Treatment completed',
            'body' => 'Your treatment :code is complete. Thank you for attending your sessions.',
        ],
    ],
    'order' => [
        'result_ready' => [
            'subject' => 'Result ready: :type for :patient',
            'body' => 'The :type result of :patient (:code) is ready to review.',
        ],
        'abnormal' => [
            'subject' => 'Abnormal result: :type for :patient',
            'body' => 'The :type result of :patient (:code) is flagged abnormal. Please review it now.',
        ],
        'patient_result_ready' => [
            'subject' => 'Your result is ready',
            'body' => 'Your result :code is ready. Your doctor will go through it with you.',
        ],
    ],
];
