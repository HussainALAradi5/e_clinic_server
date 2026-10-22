<?php

/*
| Enum labels: enums.<EnumName>.<value>. Missing keys fall back to a headline of the value.
*/

return [
    'GenericStatus' => ['pending' => 'Pending', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'],
    'ApprovalStatus' => ['draft' => 'Draft', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'],
    'SortDirection' => ['asc' => 'Ascending', 'desc' => 'Descending'],
    'Currency' => ['BHD' => 'Bahraini Dinar', 'USD' => 'US Dollar', 'SAR' => 'Saudi Riyal'],
    'Gender' => ['male' => 'Male', 'female' => 'Female'],
    'DayOfWeek' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    'PaymentMethod' => ['cash' => 'Cash', 'card' => 'Card', 'bank_transfer' => 'Bank transfer', 'benefit_pay' => 'BenefitPay', 'insurance' => 'Insurance', 'cheque' => 'Cheque'],
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

    // Identity
    'UserType' => ['admin' => 'Admin', 'doctor' => 'Doctor', 'medical_staff' => 'Medical staff', 'pharmacist' => 'Pharmacist', 'receptionist' => 'Receptionist', 'accountant' => 'Accountant', 'cashier' => 'Cashier', 'hr' => 'HR', 'patient' => 'Patient'],
    'UserStatus' => ['active' => 'Active', 'inactive' => 'Inactive', 'locked' => 'Locked', 'pending_verification' => 'Pending verification'],
    'RoleName' => ['super_admin' => 'Super admin', 'admin' => 'Admin', 'receptionist' => 'Receptionist', 'doctor' => 'Doctor', 'nurse' => 'Nurse', 'pharmacist' => 'Pharmacist', 'hr_manager' => 'HR manager', 'accountant' => 'Accountant', 'cashier' => 'Cashier', 'patient' => 'Patient'],
    'AuditAction' => ['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted', 'restored' => 'Restored', 'status_changed' => 'Status changed', 'login' => 'Login', 'logout' => 'Logout', 'login_failed' => 'Login failed', 'password_changed' => 'Password changed', 'permission_changed' => 'Permission changed', 'exported' => 'Exported', 'posted' => 'Posted', 'reversed' => 'Reversed', 'approved' => 'Approved'],
    'LoginFailureReason' => ['invalid_credentials' => 'Invalid credentials', 'account_locked' => 'Account locked', 'account_inactive' => 'Account inactive', 'too_many_attempts' => 'Too many attempts', 'invalid_2fa_code' => 'Invalid 2FA code'],
    'TaskType' => ['report_export' => 'Report export', 'audit_export' => 'Audit export', 'transaction_export' => 'Transaction export', 'payroll_calculation' => 'Payroll calculation', 'payslip_emailing' => 'Payslip e-mailing', 'fiscal_year_close' => 'Fiscal year close', 'bulk_notification' => 'Bulk notification', 'bulk_action' => 'Bulk action', 'attachments_zip' => 'Attachments ZIP', 'list_export' => 'List export'],
    'TaskCancelReason' => ['user' => 'Cancelled by user', 'failed' => 'Failed', 'timeout' => 'Timed out'],
    'AttachmentCategory' => ['medical_certificate' => 'Medical certificate', 'xray_image' => 'X-ray image', 'lab_result' => 'Lab result', 'clinical_document' => 'Clinical document', 'consent_form' => 'Consent form', 'referral_letter' => 'Referral letter', 'insurance_document' => 'Insurance document', 'id_document' => 'ID document', 'invoice_scan' => 'Invoice scan', 'payslip' => 'Payslip', 'other' => 'Other'],
    'FileScanStatus' => ['pending' => 'Scanning', 'clean' => 'Clean', 'infected' => 'Infected'],
    'CodeSymbology' => ['qr' => 'QR code', 'code128' => 'Code 128', 'datamatrix' => 'DataMatrix'],
    'ScanPurpose' => ['identify' => 'Identify', 'check_in' => 'Check in', 'verify_document' => 'Verify document', 'sample_label' => 'Sample label'],
    'NotificationChannel' => ['sms' => 'SMS', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'push' => 'Push', 'in_app' => 'In-app'],
    'DevicePlatform' => ['android' => 'Android', 'ios' => 'iOS'],
    'MobileApp' => ['patient' => 'Patient app', 'staff' => 'Staff app'],
    'NotificationStatus' => ['queued' => 'Queued', 'sent' => 'Sent', 'delivered' => 'Delivered', 'failed' => 'Failed', 'cancelled' => 'Cancelled'],
    'CompanyType' => ['manufacturer' => 'Manufacturer', 'supplier' => 'Supplier', 'insurer' => 'Insurer', 'laboratory' => 'Laboratory', 'hospital' => 'Hospital', 'other' => 'Other'],
];
