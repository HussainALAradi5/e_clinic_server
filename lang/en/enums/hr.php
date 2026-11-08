<?php

// Enum labels (enums.<EnumName>.<value>) — Human Resources.

return [
    'StaffRequestType' => ['late_excuse' => 'Late excuse', 'overtime' => 'Overtime'],
    'SettingKey' => ['late_excuse' => 'Late excuse requests', 'overtime' => 'Overtime requests'],
    'SettingKeyHelp' => [
        'late_excuse' => 'Late arrivals are deducted from pay unless the employee sends an excuse and HR accepts it. Off: lateness is only recorded.',
        'overtime' => 'Overtime is paid only after the employee sends a request and HR approves it, at the pay of the time below. Off: HR approves overtime directly.',
    ],
    'CommissionType' => ['none' => 'No commission', 'percent' => '% of revenue', 'per_visit' => 'Per visit'],
];
