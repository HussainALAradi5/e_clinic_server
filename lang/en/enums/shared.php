<?php

// Enum labels (enums.<EnumName>.<value>) — Shared (any module).

return [
    'GenericStatus' => ['pending' => 'Pending', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'],
    'ApprovalStatus' => ['draft' => 'Draft', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'],
    'SortDirection' => ['asc' => 'Ascending', 'desc' => 'Descending'],
    'Currency' => ['BHD' => 'Bahraini Dinar', 'USD' => 'US Dollar', 'SAR' => 'Saudi Riyal'],
    'Gender' => ['male' => 'Male', 'female' => 'Female'],
    'DayOfWeek' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    'PaymentMethod' => ['cash' => 'Cash', 'card' => 'Card', 'bank_transfer' => 'Bank transfer', 'benefit_pay' => 'BenefitPay', 'insurance' => 'Insurance', 'cheque' => 'Cheque', 'patient_balance' => 'Patient balance', 'online' => 'Online'],
    'DateRangePreset' => [
        'today' => 'Today', 'yesterday' => 'Yesterday', 'this_week' => 'This week', 'last_week' => 'Last week',
        'this_month' => 'This month', 'last_month' => 'Last month', 'last_7_days' => 'Last 7 days', 'last_30_days' => 'Last 30 days',
        'this_quarter' => 'This quarter', 'this_year' => 'This year', 'custom' => 'Custom',
    ],
    'FilterOperator' => [
        'eq' => 'is', 'ne' => 'is not', 'gt' => 'greater than', 'gte' => 'at least', 'lt' => 'less than', 'lte' => 'at most',
        'in' => 'is any of', 'nin' => 'is none of', 'between' => 'between', 'like' => 'contains', 'starts' => 'starts with', 'null' => 'is empty',
    ],
    'LogicalOperator' => ['and' => 'All of', 'or' => 'Any of', 'not' => 'Not'],
];
