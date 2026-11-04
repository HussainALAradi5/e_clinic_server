<?php

use App\Enums\Accounting;
use App\Enums\Appointments;
use App\Enums\Communication;
use App\Enums\Documents;
use App\Enums\HR;
use App\Enums\Identity;
use App\Enums\Payments;
use App\Enums\Pharmacy;
use App\Enums\Reference;
use App\Enums\Shared;
use App\Enums\Transactions;

return [

    /*
    |--------------------------------------------------------------------------
    | Clinic defaults
    |--------------------------------------------------------------------------
    | Clinic-local time zone (dates shown / filtered) and base currency (BHD, 3 decimals).
    */

    'timezone' => env('CLINIC_TIMEZONE', 'Asia/Bahrain'),

    'currency' => env('CLINIC_CURRENCY', 'BHD'),

    /*
    | Angular app base URL (password-reset and invitation links).
    */
    'client_url' => env('CLIENT_URL', 'http://localhost:4200'),

    /*
    | HMAC key of blind indexes for encrypted identifiers (national ID). Keep it separate from APP_KEY.
    */
    'blind_index_key' => env('BLIND_INDEX_KEY', ''),

    /*
    | First super admin created by `php artisan db:seed`. Empty password = SuperAdminSeeder::DEFAULT_PASSWORD
    | locally, a random printed one in production.
    */
    'super_admin' => [
        'email' => env('SUPER_ADMIN_EMAIL', 'admin@eclinic.local'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    /*
    | Authentication (security doc §2).
    */
    'auth' => [
        'token_ttl_hours' => ['staff' => 12, 'patient' => 24 * 7],
        'max_failed_logins' => 5,
        'lockout_minutes' => 15,
        'two_factor_challenge_minutes' => 5,
        'two_factor_max_attempts' => 5,
        // "Don't ask again on this device" after a 2FA code (password, 2FA or sign-out-everywhere changes reset it).
        'trusted_device_days' => 30,
        // "Forgot password": a 6-digit code by email, valid this long, with this many tries.
        'password_code_minutes' => 10,
        'password_code_max_attempts' => 5,
        // Fixed two-factor code for the seeded demo accounts (DemoSeeder::EMAIL_DOMAIN), outside production only.
        // Empty disables it. Real accounts always need their authenticator app or a recovery code.
        'demo_two_factor_code' => env('DEMO_TWO_FACTOR_CODE', '111111'),
        'recovery_codes' => 8,
        'password_history' => 5,
        'access_cache_minutes' => 10,
    ],

    /*
    | Generic list query (API conventions §5).
    */
    'list' => [
        'default_per_page' => 15,
        'max_per_page' => 100,
    ],

    /*
    | Bulk actions (API conventions §11): explicit ids limit, synchronous limit, "select all matching" ceiling.
    */
    /*
    | List export (Excel / PDF / CSV) and Excel import. Bigger exports run as a background task.
    */
    'transfer' => [
        'export_sync_limit' => 1000,
        'pdf_max_rows' => 2000,
        'import_max_rows' => 2000,
        'import_max_kb' => 5120,
    ],

    'bulk' => [
        'max_ids' => 500,
        'sync_limit' => 100,
        'max_matching' => 10000,
    ],

    'idempotency' => [
        'ttl_hours' => 24,
    ],

    /*
    | Private files (attachments, task results): disk and lifetime of signed download links.
    */
    'files' => [
        'disk' => env('CLINIC_FILES_DISK', 'local'),
        'link_minutes' => 5,
    ],

    /*
    | Background tasks: job timeout, how long result files are kept, optional queue per task type.
    */
    'tasks' => [
        'timeout_seconds' => 3600,
        'result_days' => 7,
        'queues' => [],
    ],

    /*
    | Enums published by GET /meta/enums (modules append theirs).
    */
    'enums' => [
        Communication\BubbleColor::class,
        Communication\BubbleStyle::class,
        Communication\ConversationType::class,
        Communication\MuteDuration::class,
        Communication\MessageKind::class,
        Communication\ParticipantRole::class,
        Communication\TicketCategory::class,
        Communication\TicketConfirmation::class,
        Shared\GenericStatus::class,
        Shared\ApprovalStatus::class,
        Shared\SortDirection::class,
        Shared\Currency::class,
        Shared\Gender::class,
        Shared\DayOfWeek::class,
        Shared\PaymentMethod::class,
        Shared\DateRangePreset::class,
        Shared\FilterOperator::class,
        Shared\LogicalOperator::class,
        Shared\FilterFieldType::class,
        Shared\ExportFormat::class,
        Identity\UserType::class,
        Identity\UserStatus::class,
        Identity\RoleName::class,
        Identity\AuditAction::class,
        Identity\LoginFailureReason::class,
        Identity\TaskType::class,
        Identity\TaskCancelReason::class,
        Identity\AttachmentCategory::class,
        Identity\FileScanStatus::class,
        Identity\CodeSymbology::class,
        Identity\ScanPurpose::class,
        Identity\NotificationChannel::class,
        Identity\DevicePlatform::class,
        Identity\MobileApp::class,
        Identity\NotificationStatus::class,
        Reference\CompanyType::class,
        Accounting\AccountType::class,
        Accounting\BalanceSide::class,
        Accounting\AccountMappingKey::class,
        Accounting\FiscalStatus::class,
        Accounting\JournalEntryStatus::class,
        Accounting\JournalEntryType::class,
        Accounting\ReportType::class,
        HR\EmploymentType::class,
        HR\ShiftType::class,
        HR\EmployeeStatus::class,
        HR\TerminationReason::class,
        HR\SalaryComponentType::class,
        HR\AttendanceStatus::class,
        HR\AttendanceSource::class,
        HR\PayrollStatus::class,
        HR\PayslipItemType::class,
        HR\StaffRequestType::class,
        HR\SettingKey::class,
        Payments\ServiceCategory::class,
        Payments\InvoiceStatus::class,
        Payments\PaymentStatus::class,
        Payments\WalletEntryType::class,
        Payments\AppointmentFeeType::class,
        Payments\OnlinePaymentStatus::class,
        Payments\AppointmentFeeStatus::class,
        Payments\InvoicePaymentState::class,
        Payments\DiscountType::class,
        Transactions\CashSessionStatus::class,
        Transactions\ExpenseCategory::class,
        Appointments\ConsentType::class,
        Documents\WrittenDocumentKind::class,
        Documents\TemplatePlaceholder::class,
        Documents\DocumentType::class,
        Appointments\BloodGroup::class,
        Appointments\RoomType::class,
        Appointments\ScheduleExceptionType::class,
        Appointments\AppointmentType::class,
        Appointments\AppointmentStatus::class,
        Appointments\AppointmentSource::class,
        Appointments\VisitTiming::class,
        Appointments\CancelledBy::class,
        Appointments\TreatmentType::class,
        Appointments\TreatmentPriority::class,
        Appointments\ClinicalOrderType::class,
        Pharmacy\MedicationForm::class,
        Pharmacy\DispenseUnit::class,
        Pharmacy\MedicationRoute::class,
        Pharmacy\StockLocation::class,
        Pharmacy\DispensationKind::class,
        Pharmacy\StockMovementType::class,
    ],

];
