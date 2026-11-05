<?php

// Notification wording (notifications.<group>.<event>.<part>) — Human Resources.

return [
    'attendance' => [
        'your_hours' => 'your working hours',
        'checked_in_on_time' => [
            'subject' => 'Checked in on time — :time',
            'body' => 'You checked in at :time, on time for :shift (:hours).',
        ],
        'checked_in_late' => [
            'subject' => 'Checked in late — :late',
            'body' => 'You checked in at :time, :late late for :shift (:hours).',
        ],
        'checked_out_on_time' => [
            'subject' => 'Checked out on time — :time',
            'body' => 'You checked out at :time after :worked, on time for :shift (:hours).',
        ],
        'checked_out_overtime' => [
            'subject' => 'Overtime today — :overtime',
            'body' => 'You checked out at :time after :worked: :overtime overtime on :shift (:hours). It is waiting for HR approval.',
        ],
        'checked_out_early' => [
            'subject' => 'Left early — :early',
            'body' => 'You checked out at :time after :worked, :early before the end of :shift (:hours).',
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
    'staff_request' => [
        'requested' => [
            'subject' => ':type to review: :employee',
            'body' => ':employee asks for :type on :date (:minutes), request :code.',
        ],
        'approved' => [
            'subject' => 'Request approved: :type on :date',
            'body' => 'Your :type request for :date (:minutes) is approved. :reason',
        ],
        'rejected' => [
            'subject' => 'Request not approved: :type on :date',
            'body' => 'Your :type request for :date (:minutes) was not approved: :reason',
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
