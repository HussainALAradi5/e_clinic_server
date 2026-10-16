<?php

use App\Enums\Identity;
use App\Enums\Reference;
use App\Shared\Enums;

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
        Enums\GenericStatus::class,
        Enums\ApprovalStatus::class,
        Enums\SortDirection::class,
        Enums\Currency::class,
        Enums\Gender::class,
        Enums\DayOfWeek::class,
        Enums\PaymentMethod::class,
        Enums\DateRangePreset::class,
        Enums\FilterOperator::class,
        Enums\LogicalOperator::class,
        Enums\FilterFieldType::class,
        Enums\ExportFormat::class,
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
    ],

];
