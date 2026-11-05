<?php

// Enum labels (enums.<EnumName>.<value>) — Identity & System.

return [
    'UserType' => ['admin' => 'Admin', 'doctor' => 'Doctor', 'medical_staff' => 'Medical staff', 'pharmacist' => 'Pharmacist', 'receptionist' => 'Receptionist', 'accountant' => 'Accountant', 'cashier' => 'Cashier', 'hr' => 'HR', 'patient' => 'Patient'],
    'UserStatus' => ['active' => 'Active', 'inactive' => 'Inactive', 'locked' => 'Locked', 'pending_verification' => 'Pending verification'],
    'RoleName' => ['super_admin' => 'Super admin', 'admin' => 'Admin', 'receptionist' => 'Receptionist', 'doctor' => 'Doctor', 'nurse' => 'Nurse', 'pharmacist' => 'Pharmacist', 'hr_manager' => 'HR manager', 'accountant' => 'Accountant', 'cashier' => 'Cashier', 'support' => 'Support team', 'patient' => 'Patient'],
    'AuditAction' => ['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted', 'restored' => 'Restored', 'status_changed' => 'Status changed', 'login' => 'Login', 'logout' => 'Logout', 'login_failed' => 'Login failed', 'password_changed' => 'Password changed', 'permission_changed' => 'Permission changed', 'exported' => 'Exported', 'posted' => 'Posted', 'reversed' => 'Reversed', 'approved' => 'Approved', 'emergency_access' => 'Emergency access'],
    'LoginFailureReason' => ['invalid_credentials' => 'Invalid credentials', 'account_locked' => 'Account locked', 'account_inactive' => 'Account inactive', 'too_many_attempts' => 'Too many attempts', 'invalid_2fa_code' => 'Invalid 2FA code'],
    'TaskType' => ['report_export' => 'Report export', 'audit_export' => 'Audit export', 'transaction_export' => 'Transaction export', 'payroll_calculation' => 'Payroll calculation', 'payslip_emailing' => 'Payslip e-mailing', 'fiscal_year_close' => 'Fiscal year close', 'bulk_notification' => 'Bulk notification', 'bulk_action' => 'Bulk action', 'attachments_zip' => 'Attachments ZIP', 'list_export' => 'List export'],
    'TaskCancelReason' => ['user' => 'Cancelled by user', 'failed' => 'Failed', 'timeout' => 'Timed out'],
    'AttachmentCategory' => ['medical_certificate' => 'Medical certificate', 'xray_image' => 'X-ray image', 'lab_result' => 'Lab result', 'clinical_document' => 'Clinical document', 'consent_form' => 'Consent form', 'referral_letter' => 'Referral letter', 'insurance_document' => 'Insurance document', 'id_document' => 'ID document', 'invoice_scan' => 'Invoice scan', 'payslip' => 'Payslip', 'other' => 'Other', 'chat_media' => 'Chat file', 'voice_note' => 'Voice note', 'ticket_attachment' => 'Ticket file', 'generated_document' => 'Generated document', 'signature' => 'Signature', 'clinic_logo' => 'Clinic logo'],
    'FileScanStatus' => ['pending' => 'Scanning', 'clean' => 'Clean', 'infected' => 'Infected'],
    'CodeSymbology' => ['qr' => 'QR code', 'code128' => 'Code 128', 'datamatrix' => 'DataMatrix'],
    'ScanPurpose' => ['identify' => 'Identify', 'check_in' => 'Check in', 'verify_document' => 'Verify document', 'sample_label' => 'Sample label'],
    'NotificationChannel' => ['sms' => 'SMS', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'push' => 'Push', 'in_app' => 'In-app'],
    'DevicePlatform' => ['android' => 'Android', 'ios' => 'iOS'],
    'MobileApp' => ['patient' => 'Patient app', 'staff' => 'Staff app'],
    'NotificationStatus' => ['queued' => 'Queued', 'sent' => 'Sent', 'delivered' => 'Delivered', 'failed' => 'Failed', 'cancelled' => 'Cancelled'],
];
