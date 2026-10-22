<?php

use App\Modules\Identity\Attachments\Http\Controllers\AttachmentController;
use App\Modules\Identity\Audit\Http\Controllers\AuditLogController;
use App\Modules\Identity\Auth\Http\Controllers\AuthController;
use App\Modules\Identity\Codes\Http\Controllers\CodeController;
use App\Modules\Identity\Notifications\Http\Controllers\NotificationController;
use App\Modules\Identity\Roles\Http\Controllers\RoleController;
use App\Modules\Identity\Tasks\Http\Controllers\BackgroundTaskController;
use App\Modules\Identity\Users\Http\Controllers\UserController;
use App\Modules\Identity\Views\Http\Controllers\ListViewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Identity API (prefix /api/v1) — Auth, People & Users, Roles & Permissions, Audit Logs, Background Tasks,
| Attachments, Codes, Notifications, List Views
|--------------------------------------------------------------------------
*/

// Authentication — public endpoints
Route::prefix('auth')->name('auth.')->middleware('throttle:auth')->controller(AuthController::class)->group(function (): void {
    Route::post('login', 'login')->name('login');
    Route::post('two-factor/challenge', 'twoFactorChallenge')->name('two-factor.challenge');
    Route::post('forgot-password', 'forgotPassword')->name('forgot-password');
    Route::post('verify-reset-code', 'verifyResetCode')->name('verify-reset-code');
    Route::post('reset-password', 'resetPassword')->name('reset-password');
});

// Public: document verification (printed codes) and provider delivery receipts (HMAC-signed)
Route::middleware('throttle:public')->group(function (): void {
    Route::get('public/verify/{payload}', [CodeController::class, 'verify'])->where('payload', '[A-Za-z0-9_:.\\-]+')->name('codes.verify');
    Route::post('webhooks/notifications/{provider}', [NotificationController::class, 'webhook'])->where('provider', '[a-z0-9_]+')->name('notifications.webhook');
});

Route::middleware('auth:sanctum')->group(function (): void {

    // Authentication — signed-in user
    Route::prefix('auth')->name('auth.')->controller(AuthController::class)->group(function (): void {
        Route::get('me', 'me')->middleware('throttle:read')->name('me');
        Route::post('logout', 'logout')->middleware('throttle:write')->name('logout');
        Route::middleware('throttle:sensitive')->group(function (): void {
            Route::post('logout-all', 'logoutAll')->name('logout-all');
            Route::put('password', 'changePassword')->name('password');
            Route::post('two-factor/enable', 'enableTwoFactor')->name('two-factor.enable');
            Route::post('two-factor/confirm', 'confirmTwoFactor')->name('two-factor.confirm');
            Route::delete('two-factor', 'disableTwoFactor')->name('two-factor.disable');
        });
    });

    // People & Users
    Route::controller(UserController::class)->group(function (): void {
        Route::get('people', 'searchPeople')->middleware(['access:people.read_all|people.read_own', 'throttle:read'])->name('people.index');
        Route::post('people/duplicates', 'duplicates')->middleware(['access:people.read_all|people.read_own', 'throttle:write'])->name('people.duplicates');
        Route::put('people/{person}', 'updatePerson')->whereUuid('person')->middleware(['access:people.write', 'throttle:write'])->name('people.update');

        Route::get('users', 'index')->middleware(['access:users.read_all|users.read_own', 'throttle:read'])->name('users.index');
        Route::get('users/{user}', 'show')->whereUuid('user')->middleware(['access:users.read_all|users.read_own', 'throttle:read'])->name('users.show');
        Route::patch('users/{user}/status', 'changeStatus')->whereUuid('user')->middleware(['access:users.write&users.change_status', 'ensure.2fa', 'throttle:sensitive'])->name('users.status');
        Route::post('users/{user}/send-reset-link', 'sendResetLink')->whereUuid('user')->middleware(['access:users.write', 'throttle:sensitive'])->name('users.send-reset-link');
    });

    // Roles & Permissions
    Route::get('roles/me/access', [RoleController::class, 'myAccess'])->middleware('throttle:read')->name('roles.me.access');

    Route::prefix('roles')->name('roles.')->middleware('ensure.2fa')->controller(RoleController::class)->group(function (): void {
        Route::middleware(['access:roles.read_all|roles.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('permissions', 'permissions')->name('permissions');
            Route::get('resources', 'resources')->name('resources');
            Route::get('{role}', 'show')->whereUuid('role')->name('show');
            Route::get('{role}/access', 'access')->whereUuid('role')->name('access');
        });

        Route::middleware('throttle:sensitive')->group(function (): void {
            Route::post('/', 'store')->middleware('access:roles.create')->name('store');
            Route::put('{role}', 'update')->whereUuid('role')->middleware('access:roles.write')->name('update');
            Route::delete('{role}', 'destroy')->whereUuid('role')->middleware('access:roles.delete')->name('destroy');
            Route::patch('{role}/restore', 'restoreRole')->whereUuid('role')->middleware('access:roles.delete')->name('restore');
            Route::put('{role}/access', 'updateAccess')->whereUuid('role')->middleware('access:roles.write')->name('access.update');
            Route::put('users/{user}', 'syncUserRoles')->whereUuid('user')->middleware('access:roles.write&roles.assign')->name('users.sync');
        });

        Route::middleware(['access:roles.write', 'throttle:write'])->group(function (): void {
            Route::patch('{role}/archive', 'archiveRole')->whereUuid('role')->name('archive');
            Route::patch('{role}/unarchive', 'unarchiveRole')->whereUuid('role')->name('unarchive');
        });
    });
    Route::bulkActions('roles', 'roles')->middleware(['ensure.2fa', 'access:roles.write|roles.delete']);
    Route::middleware('ensure.2fa')->group(fn () => Route::dataTransfer('roles', 'roles'));

    // Audit logs
    Route::prefix('audit-logs')->name('audit-logs.')->middleware(['access:audit.read_all|audit.read_own', 'throttle:read'])->controller(AuditLogController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('{type}/{id}', 'forModel')->where('type', '[a-z_]+')->whereUuid('id')->name('for-model');
    });

    // Background tasks (own tasks; administrators see all)
    Route::prefix('tasks')->name('tasks.')->controller(BackgroundTaskController::class)->group(function (): void {
        Route::get('/', 'index')->middleware('throttle:read')->name('index');
        Route::get('{task}', 'show')->whereUuid('task')->middleware('throttle:read')->name('show');
        Route::post('{task}/cancel', 'cancel')->whereUuid('task')->middleware('throttle:write')->name('cancel');
        Route::get('{task}/download', 'download')->whereUuid('task')->middleware('throttle:report')->name('download');
    });

    // Attachments (owner-record access rules apply to its files)
    Route::prefix('attachments')->name('attachments.')->controller(AttachmentController::class)->group(function (): void {
        Route::middleware(['access:attachments.create', 'throttle:write'])->group(function (): void {
            Route::post('/', 'store')->name('store');
            Route::post('uploads', 'startUpload')->name('uploads.start');
            Route::patch('uploads/{upload}', 'appendChunk')->whereUuid('upload')->withoutMiddleware('throttle:write')->middleware('throttle:upload')->name('uploads.append');
            Route::get('uploads/{upload}', 'uploadStatus')->whereUuid('upload')->name('uploads.show');
            Route::post('uploads/{upload}/complete', 'completeUpload')->whereUuid('upload')->name('uploads.complete');
            Route::delete('uploads/{upload}', 'cancelUpload')->whereUuid('upload')->name('uploads.cancel');
        });
        Route::middleware(['access:attachments.read_all|attachments.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{attachment}', 'show')->whereUuid('attachment')->name('show');
        });
        Route::get('{attachment}/download', 'download')->whereUuid('attachment')->middleware(['access:attachments.read_all|attachments.read_own', 'throttle:report'])->name('download');
        Route::post('zip', 'zip')->middleware(['access:attachments.read_all|attachments.read_own', 'throttle:report'])->name('zip');
        Route::middleware(['access:attachments.write', 'throttle:write'])->group(function (): void {
            Route::patch('{attachment}/archive', 'archive')->whereUuid('attachment')->name('archive');
            Route::patch('{attachment}/unarchive', 'unarchive')->whereUuid('attachment')->name('unarchive');
        });
        Route::middleware(['access:attachments.delete', 'throttle:write'])->group(function (): void {
            Route::delete('{attachment}', 'destroy')->whereUuid('attachment')->name('destroy');
            Route::patch('{attachment}/restore', 'restore')->whereUuid('attachment')->name('restore');
        });
    });
    Route::bulkActions('attachments', 'attachments')->middleware('access:attachments.write|attachments.delete');
    Route::dataTransfer('attachments', 'attachments');

    // QR codes & barcodes
    Route::prefix('codes')->name('codes.')->controller(CodeController::class)->group(function (): void {
        Route::post('scan', 'scan')->middleware(['access:(codes.read_all|codes.read_own)&codes.scan', 'throttle:write'])->name('scan');
        Route::get('{entity}/{id}', 'image')->where('entity', '[a-z_]+')->whereUuid('id')->middleware(['access:(codes.read_all|codes.read_own)&codes.generate', 'throttle:read'])->name('image');
    });

    // Notifications — own inbox & devices (any user), delivery log (permission)
    Route::prefix('notifications')->name('notifications.')->controller(NotificationController::class)->group(function (): void {
        Route::get('mine', 'inbox')->middleware('throttle:read')->name('inbox');
        Route::patch('mine/read-all', 'markAllRead')->middleware('throttle:write')->name('read-all');
        Route::patch('{notification}/read', 'markRead')->whereUuid('notification')->middleware('throttle:write')->name('read');
        Route::post('devices', 'registerDevice')->middleware('throttle:write')->name('devices.store');
        Route::delete('devices/{token}', 'unregisterDevice')->where('token', '[^/]{1,255}')->middleware('throttle:write')->name('devices.destroy');

        Route::middleware(['access:notifications.read_all|notifications.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{notification}', 'show')->whereUuid('notification')->name('show');
        });
        Route::post('{notification}/resend', 'resend')->whereUuid('notification')->middleware(['access:notifications.write', 'throttle:sensitive'])->name('resend');
        Route::patch('{notification}/cancel', 'cancel')->whereUuid('notification')->middleware(['access:notifications.write', 'throttle:write'])->name('cancel');
    });
    Route::bulkActions('notifications', 'notifications')->middleware('access:notifications.write|notifications.delete');
    Route::dataTransfer('notifications', 'notifications');

    // Export (Excel / PDF / CSV) and Excel import of the identity lists — `<resource>.export|import`, never patients.
    Route::middleware('ensure.2fa')->group(fn () => Route::dataTransfer('users', 'users'));
    Route::dataTransfer('people', 'people');
    Route::dataTransfer('audit-logs', 'audit');

    // Saved list views (personal; shared views need <resource>.read_all)
    Route::prefix('views')->name('views.')->controller(ListViewController::class)->group(function (): void {
        Route::get('/', 'index')->middleware('throttle:read')->name('index');
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('/', 'store')->name('store');
            Route::put('{view}', 'update')->whereUuid('view')->name('update');
            Route::patch('{view}/default', 'setDefault')->whereUuid('view')->name('default');
            Route::delete('{view}', 'destroy')->whereUuid('view')->name('destroy');
        });
    });
});
