<?php

/*
| Notification templates — minimum PHI (no diagnoses; "your result is ready" + portal link).
*/

return [
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
    'refund' => [
        'requested' => [
            'subject' => 'Refund to approve: :amount',
            'body' => 'Refund :code of :amount for :patient is waiting for your approval.',
        ],
        'approved' => [
            'subject' => 'Refund approved: :amount',
            'body' => 'Refund :code of :amount for :patient is approved and can be paid out.',
        ],
        'rejected' => [
            'subject' => 'Refund rejected: :code',
            'body' => 'Refund :code of :amount for :patient was rejected: :reason',
        ],
    ],
    'expense' => [
        'submitted' => [
            'subject' => 'Expense to approve: :amount',
            'body' => 'Expense :code of :amount to :payee is waiting for your approval.',
        ],
        'approved' => [
            'subject' => 'Expense approved: :code',
            'body' => 'Your expense :code of :amount to :payee is approved and will be paid.',
        ],
        'rejected' => [
            'subject' => 'Expense rejected: :code',
            'body' => 'Your expense :code of :amount to :payee was rejected: :reason',
        ],
    ],
    'prescription' => [
        'created' => [
            'subject' => 'Prescription :code is ready at the pharmacy',
            'body' => 'Dr :doctor wrote you prescription :code. Show it at the clinic pharmacy before :valid_until.',
        ],
    ],
    'pharmacy' => [
        'stock_alert' => [
            'subject' => 'Pharmacy stock needs attention',
            'body' => ':low medicine(s) at or below their reorder level and :expiring lot(s) expiring within :days days.',
        ],
    ],
    'task' => [
        'completed' => [
            'subject' => ':task finished',
            'body' => 'Your task ":task" finished successfully.',
        ],
        'failed' => [
            'subject' => ':task failed',
            'body' => 'Your task ":task" could not be completed: :reason',
        ],
    ],
    'leave' => [
        'requested' => [
            'subject' => 'Leave to approve: :employee',
            'body' => ':employee asks for :type from :from to :to (:days day(s)), request :code.',
        ],
        'approved' => [
            'subject' => 'Leave approved: :from – :to',
            'body' => 'Your :type from :from to :to (:days day(s)) is approved. :notes',
        ],
        'rejected' => [
            'subject' => 'Leave not approved: :code',
            'body' => 'Your :type from :from to :to was not approved: :notes',
        ],
    ],
    'payroll' => [
        'calculated' => [
            'subject' => 'Payroll to approve: :month',
            'body' => 'Payroll :code for :month is calculated — :count payslips, :net net. It is waiting for your approval.',
        ],
        'payslip_ready' => [
            'subject' => 'Your payslip for :month',
            'body' => 'Your salary for :month has been paid: :net net. The payslip is under My HR → Payslips.',
        ],
    ],
];
