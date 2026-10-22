<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Appointments (front desk rules)
    |--------------------------------------------------------------------------
    */

    /* Furthest date that can be booked (days from today). */
    'booking_horizon_days' => 90,

    /* Portal bookings must start at least this many hours ahead; they start as `pending`. */
    'portal_min_hours_ahead' => 2,

    /* Patients may cancel / reschedule up to this many hours before the start (staff: any time before check-in). */
    'patient_change_hours' => 2,

    /* A confirmed booking not checked in this many minutes after its start becomes `no_show` (scheduler). */
    'no_show_after_minutes' => 15,

    /* Slot length when neither the schedule block nor the doctor sets one. */
    'default_slot_minutes' => 15,

    /* Reminder sent this many hours before the start. */
    'reminder_hours_before' => 24,

    /* Treatment plans. */
    'treatments' => [
        /* Default session length per treatment type (minutes); `default` for the rest. */
        'session_minutes' => [
            'physiotherapy' => 45,
            'rehabilitation' => 45,
            'wound_care' => 20,
            'injection' => 15,
            'infusion' => 60,
            'dialysis' => 240,
            'respiratory_therapy' => 30,
            'laser' => 30,
            'dental' => 30,
            'default' => 30,
        ],
        /* Missed sessions in a row that alert the doctor and the front desk (the plan is not cancelled). */
        'absence_alert_after' => 2,
    ],

];
