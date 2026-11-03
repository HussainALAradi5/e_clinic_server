<?php

/*
| Enum labels: enums.<EnumName>.<value>. Missing keys fall back to the value in sentence case (`checked_in` → `Checked in`);
| list here only the words that need care (abbreviations, hyphens, clinic wording).
*/

return [
    'GenericStatus' => ['pending' => 'Pending', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'],
    'ConversationType' => ['direct' => 'One-to-one', 'group' => 'Group'],
    'ParticipantRole' => ['admin' => 'Leader', 'member' => 'Member'],
    'MessageKind' => ['text' => 'Text', 'media' => 'Photo / file', 'voice' => 'Voice note'],
    'BubbleStyle' => ['rounded' => 'Rounded', 'square' => 'Square', 'minimal' => 'Minimal'],
    'BubbleColor' => ['blue' => 'Blue', 'green' => 'Green', 'teal' => 'Teal', 'purple' => 'Purple', 'rose' => 'Rose', 'slate' => 'Slate'],
    'TicketConfirmation' => ['awaiting' => 'Awaiting confirmation', 'confirmed' => 'Confirmed fixed', 'declined' => 'Declined — not fixed'],
    'TicketCategory' => ['technical' => 'Technical problem', 'access' => 'Access & accounts', 'equipment' => 'Equipment', 'data' => 'Wrong data', 'other' => 'Other'],
    'StaffRequestType' => ['late_excuse' => 'Late excuse', 'overtime' => 'Overtime'],
    'SettingKey' => ['late_excuse' => 'Late excuse requests', 'overtime' => 'Overtime requests'],
    'SettingKeyHelp' => [
        'late_excuse' => 'Late arrivals are deducted from pay unless the employee sends an excuse and HR accepts it. Off: lateness is only recorded.',
        'overtime' => 'Overtime is paid only after the employee sends a request and HR approves it, at the pay per hour below (empty: hourly wage × 1.25). Off: HR approves overtime directly.',
    ],
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
    'RoleName' => ['super_admin' => 'Super admin', 'admin' => 'Admin', 'receptionist' => 'Receptionist', 'doctor' => 'Doctor', 'nurse' => 'Nurse', 'pharmacist' => 'Pharmacist', 'hr_manager' => 'HR manager', 'accountant' => 'Accountant', 'cashier' => 'Cashier', 'support' => 'Support team', 'patient' => 'Patient'],
    'AuditAction' => ['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted', 'restored' => 'Restored', 'status_changed' => 'Status changed', 'login' => 'Login', 'logout' => 'Logout', 'login_failed' => 'Login failed', 'password_changed' => 'Password changed', 'permission_changed' => 'Permission changed', 'exported' => 'Exported', 'posted' => 'Posted', 'reversed' => 'Reversed', 'approved' => 'Approved', 'emergency_access' => 'Emergency access'],
    'LoginFailureReason' => ['invalid_credentials' => 'Invalid credentials', 'account_locked' => 'Account locked', 'account_inactive' => 'Account inactive', 'too_many_attempts' => 'Too many attempts', 'invalid_2fa_code' => 'Invalid 2FA code'],
    'TaskType' => ['report_export' => 'Report export', 'audit_export' => 'Audit export', 'transaction_export' => 'Transaction export', 'payroll_calculation' => 'Payroll calculation', 'payslip_emailing' => 'Payslip e-mailing', 'fiscal_year_close' => 'Fiscal year close', 'bulk_notification' => 'Bulk notification', 'bulk_action' => 'Bulk action', 'attachments_zip' => 'Attachments ZIP', 'list_export' => 'List export'],
    'TaskCancelReason' => ['user' => 'Cancelled by user', 'failed' => 'Failed', 'timeout' => 'Timed out'],
    'AttachmentCategory' => ['medical_certificate' => 'Medical certificate', 'xray_image' => 'X-ray image', 'lab_result' => 'Lab result', 'clinical_document' => 'Clinical document', 'consent_form' => 'Consent form', 'referral_letter' => 'Referral letter', 'insurance_document' => 'Insurance document', 'id_document' => 'ID document', 'invoice_scan' => 'Invoice scan', 'payslip' => 'Payslip', 'other' => 'Other', 'chat_media' => 'Chat file', 'voice_note' => 'Voice note', 'ticket_attachment' => 'Ticket file'],
    'FileScanStatus' => ['pending' => 'Scanning', 'clean' => 'Clean', 'infected' => 'Infected'],
    'CodeSymbology' => ['qr' => 'QR code', 'code128' => 'Code 128', 'datamatrix' => 'DataMatrix'],
    'ScanPurpose' => ['identify' => 'Identify', 'check_in' => 'Check in', 'verify_document' => 'Verify document', 'sample_label' => 'Sample label'],
    'NotificationChannel' => ['sms' => 'SMS', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'push' => 'Push', 'in_app' => 'In-app'],
    'DevicePlatform' => ['android' => 'Android', 'ios' => 'iOS'],
    'MobileApp' => ['patient' => 'Patient app', 'staff' => 'Staff app'],
    'NotificationStatus' => ['queued' => 'Queued', 'sent' => 'Sent', 'delivered' => 'Delivered', 'failed' => 'Failed', 'cancelled' => 'Cancelled'],
    'CompanyType' => ['manufacturer' => 'Manufacturer', 'supplier' => 'Supplier', 'insurer' => 'Insurer', 'laboratory' => 'Laboratory', 'hospital' => 'Hospital', 'other' => 'Other'],

    // Words that need care — the rest read fine in sentence case.
    'AppointmentSource' => ['walk_in' => 'Walk-in', 'phone' => 'Phone', 'portal' => 'Online', 'referral' => 'Referral'],
    'AppointmentStatus' => ['checked_in' => 'Checked in', 'no_show' => 'No-show'],
    'VisitTiming' => ['coming' => 'Coming', 'today' => 'Today', 'past' => 'Past', 'missed' => 'Missed'],
    'AppointmentType' => ['follow_up' => 'Follow-up', 'teleconsultation' => 'Video consultation', 'treatment_session' => 'Treatment session'],
    'CancelledBy' => ['system' => 'Automatically'],
    'ClinicalOrderType' => ['xray' => 'X-ray', 'ultrasound' => 'Ultrasound', 'ct' => 'CT scan', 'mri' => 'MRI scan', 'lab_test' => 'Lab test', 'ecg' => 'ECG', 'referral' => 'Referral'],
    'RoomType' => ['physical' => 'Physiotherapy', 'xray' => 'X-ray', 'session_room' => 'Treatment sessions', 'dental' => 'Dental', 'procedure' => 'Procedure room', 'vaccination' => 'Vaccination'],
    'DispensationKind' => ['take_home' => 'Taken home', 'administered' => 'Given at the clinic'],
    'DispenseUnit' => ['ml' => 'ml'],
    'StockMovementType' => ['receipt' => 'Delivery received', 'dispense' => 'Dispensed', 'return' => 'Returned', 'expiry_writeoff' => 'Expired — written off'],
    'AccountMappingKey' => ['input_vat' => 'Input VAT', 'vat_payable' => 'VAT payable', 'cash_over_short' => 'Cash over / short'],
    'TransactionType' => ['payment_in' => 'Payment received', 'refund_out' => 'Refund paid out', 'salary_out' => 'Salaries paid', 'expense_out' => 'Expense paid', 'bank_deposit' => 'Bank deposit', 'cash_adjustment' => 'Cash adjustment'],
    'TransactionDirection' => ['in' => 'Money in', 'out' => 'Money out', 'internal' => 'Transfer'],
    'ReportType' => ['revenue_by_doctor' => 'Revenue by doctor', 'revenue_by_service' => 'Revenue by service', 'receivables_aging' => 'Unpaid invoices by age'],
];
