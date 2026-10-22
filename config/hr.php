<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Human Resources — attendance, leaves and payroll
    |--------------------------------------------------------------------------
    */

    'attendance' => [
        /*
        | Web check-in / check-out is accepted only from these networks (CIDR, IPv4 or IPv6). Comma-separated in
        | HR_CHECK_IN_NETWORKS; an empty value accepts any address (not recommended in production).
        */
        'networks' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'HR_CHECK_IN_NETWORKS',
            '127.0.0.0/8,::1/128,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16',
        ))))),

        /* Minutes after the shift start before a check-in counts as late. */
        'late_grace_minutes' => 10,

        /* Minutes worked past the shift end below this are not counted as overtime. */
        'overtime_min_minutes' => 15,
    ],

    'payroll' => [
        /* Daily rate = basic salary / this; also used to pro-rate joiners and leavers by calendar days. */
        'days_per_month' => 30,

        /* Hourly rate = daily rate / this. */
        'hours_per_day' => 8,

        /* Overtime is paid at the hourly rate × this. */
        'overtime_multiplier' => '1.25',

        /*
        | Employee share of social insurance (percent of basic + taxable allowances), by nationality (ISO code);
        | `default` applies to everyone else. Bahrain: 7 % for Bahraini staff, 1 % (unemployment) for expatriates.
        */
        'social_insurance_rates' => [
            'BH' => '7',
            'default' => '1',
        ],

        /* Chart account (code) credited with other payroll deductions withheld from staff (loans, advances, fines). */
        'deductions_account' => '2400',
    ],

];
