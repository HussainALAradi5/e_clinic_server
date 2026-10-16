<?php

use App\Modules\Identity\Attachments\Models\Attachment;
use App\Modules\Identity\Notifications\Models\Notification;
use App\Modules\Identity\Roles\Models\Role;
use App\Modules\Identity\Tasks\Models\BackgroundTask;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Identity\Users\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Audit trail (Identity › Audit Logs)
    |--------------------------------------------------------------------------
    */

    /* Values replaced by `***` in old / new values (plus each model's auditMasked()). */
    'masked' => ['password', 'remember_token', 'token', 'two_factor_secret', 'two_factor_recovery_codes', 'iban', 'national_id', 'national_id_hash'],

    /* Changes to only these attributes do not create an audit row. */
    'ignored' => ['created_at', 'updated_at', 'version', 'created_by', 'updated_by', 'deleted_at', 'deleted_by'],

    /* Queue of the WriteAuditLog job (null = default queue). */
    'queue' => env('AUDIT_QUEUE'),

    /* Largest date range of one list query (days). */
    'max_range_days' => 93,

    /* Public morph aliases (Relation::morphMap) and `GET /audit-logs/{type}/{id}` types. Never a raw class name from the client. Modules add theirs. */
    'models' => [
        'person' => Person::class,
        'user' => User::class,
        'role' => Role::class,
        'background_task' => BackgroundTask::class,
        'attachment' => Attachment::class,
        'notification' => Notification::class,
    ],

];
