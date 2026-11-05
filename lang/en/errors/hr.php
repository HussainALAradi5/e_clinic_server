<?php

// Error messages by error code — Human Resources.

return [
    'DEPARTMENT_CYCLE' => 'The chosen parent would put the department under itself.',
    'DEPARTMENT_HAS_EMPLOYEES' => 'The department (or one below it) still has employees. Move them first or archive the department.',
    'ALREADY_EMPLOYEE' => 'This person is already an employee.',
    'EMPLOYEE_TERMINATED' => 'This employee has been terminated; the record is read-only.',
    'ATTENDANCE_ALREADY_CHECKED_IN' => 'You already checked in today at :time.',
    'ATTENDANCE_NOT_CHECKED_IN' => 'There is no open check-in today to check out from.',
    'STAFF_REQUEST_DISABLED' => 'HR has switched this kind of request off.',
    'STAFF_REQUEST_EXISTS' => 'This day already has a request of this kind.',
    'STAFF_REQUEST_NOTHING_TO_ASK' => 'There is nothing to ask about for this day (no late minutes or overtime, or already settled).',
    'STAFF_REQUEST_NOT_YOURS' => 'Only the employee who sent the request can withdraw it.',
    'OVERTIME_NEEDS_REQUEST' => 'Overtime requests are on: approve the employee\'s overtime request under Requests.',
    'ATTENDANCE_LOCKED' => 'Attendance of :month is in an approved payroll and can no longer be changed.',
    'ATTENDANCE_NETWORK_NOT_ALLOWED' => 'Check in and out at the clinic: on the clinic network, or in the app with your location on.',
    'LEAVE_ATTACHMENT_REQUIRED' => ':type needs a supporting document (e.g. a medical certificate) before it can be sent.',
    'LEAVE_OVERLAP' => 'There is already a pending or approved leave (:code) between these dates.',
    'LEAVE_INSUFFICIENT_BALANCE' => 'Only :remaining day(s) of :type are left this year; this request needs :days.',
    'LEAVE_NOTICE' => ':type must be requested at least :days day(s) ahead.',
    'LEAVE_ONE_YEAR' => 'A leave request stays within one calendar year — split it at 31 December.',
    'LEAVE_HALF_DAY' => 'A half day is a single date.',
    'LEAVE_NO_WORKING_DAYS' => 'These dates contain no working day (weekly days off and clinic holidays are not counted).',
    'PAYROLL_PERIOD_EXISTS' => 'There is already a payroll for :month (:code).',
    'PAYROLL_PERIOD_NOT_ENDED' => 'The payroll of :month can be calculated from :date.',
    'SHIFT_HAS_EMPLOYEES' => 'People are on this shift — move them to another shift first, or archive it.',
    'SHIFT_UNKNOWN_EMPLOYEE' => 'One of these people is not an active employee.',
    'SHIFT_HOURS_DO_NOT_FIT' => 'The hours due do not fit between the earliest start and the latest end.',
];
