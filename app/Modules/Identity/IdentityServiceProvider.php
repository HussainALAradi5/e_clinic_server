<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Enums\Identity\TaskType;
use App\Modules\Identity\Attachments\Contracts\AttachmentServiceInterface;
use App\Modules\Identity\Attachments\Contracts\FileScanner;
use App\Modules\Identity\Attachments\Events\AttachmentUploaded;
use App\Modules\Identity\Attachments\Listeners\QueueAttachmentScan;
use App\Modules\Identity\Attachments\Models\Attachment;
use App\Modules\Identity\Attachments\Scanners\ClamAvScanner;
use App\Modules\Identity\Attachments\Scanners\NullScanner;
use App\Modules\Identity\Attachments\Services\AttachmentService;
use App\Modules\Identity\Attachments\Tasks\AttachmentsZipTaskHandler;
use App\Modules\Identity\Audit\Contracts\AuditableEvent;
use App\Modules\Identity\Audit\Contracts\AuditLogServiceInterface;
use App\Modules\Identity\Audit\Listeners\RecordAuditableEvent;
use App\Modules\Identity\Audit\Models\AuditLog;
use App\Modules\Identity\Audit\Services\AuditLogService;
use App\Modules\Identity\Audit\Tasks\AuditExportTaskHandler;
use App\Modules\Identity\Auth\Contracts\AuthServiceInterface;
use App\Modules\Identity\Auth\Events\UserLoggedOut;
use App\Modules\Identity\Auth\Listeners\SendAccountInvitation;
use App\Modules\Identity\Auth\Models\PersonalAccessToken;
use App\Modules\Identity\Auth\Services\AuthService;
use App\Modules\Identity\Auth\Support\PersonEmailUserProvider;
use App\Modules\Identity\Codes\Contracts\CodeServiceInterface;
use App\Modules\Identity\Codes\Services\CodeService;
use App\Modules\Identity\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Identity\Notifications\Listeners\ForgetDevicesOnLogoutAll;
use App\Modules\Identity\Notifications\Models\Notification;
use App\Modules\Identity\Notifications\Services\NotificationService;
use App\Modules\Identity\Roles\Contracts\RoleServiceInterface;
use App\Modules\Identity\Roles\Models\Role;
use App\Modules\Identity\Roles\Services\RoleService;
use App\Modules\Identity\Tasks\Console\PurgeTaskResults;
use App\Modules\Identity\Tasks\Contracts\BackgroundTaskServiceInterface;
use App\Modules\Identity\Tasks\Events\TaskCancelled;
use App\Modules\Identity\Tasks\Events\TaskCompleted;
use App\Modules\Identity\Tasks\Listeners\NotifyTaskOwner;
use App\Modules\Identity\Tasks\Models\BackgroundTask;
use App\Modules\Identity\Tasks\Services\BackgroundTaskService;
use App\Modules\Identity\Tasks\Support\TaskHandlerRegistry;
use App\Modules\Identity\Users\Contracts\UserServiceInterface;
use App\Modules\Identity\Users\Events\UserCreated;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Identity\Users\Models\User;
use App\Modules\Identity\Users\Services\UserService;
use App\Modules\Identity\Views\Contracts\ListViewServiceInterface;
use App\Modules\Identity\Views\Models\SavedView;
use App\Modules\Identity\Views\Services\ListViewService;
use App\Shared\Access\PermissionExpression;
use App\Shared\Bulk\BulkActionRegistry;
use App\Shared\Bulk\BulkActionTaskHandler;
use App\Shared\Contracts\AuditRecorder;
use App\Shared\Contracts\TaskDispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

/**
 * Identity module: authentication, people & users, roles & permissions, audit logs, background tasks,
 * attachments, QR / barcodes, notifications and saved list views.
 */
final class IdentityServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        AuthServiceInterface::class => AuthService::class,
        UserServiceInterface::class => UserService::class,
        RoleServiceInterface::class => RoleService::class,
        AuditLogServiceInterface::class => AuditLogService::class,
        BackgroundTaskServiceInterface::class => BackgroundTaskService::class,
        AttachmentServiceInterface::class => AttachmentService::class,
        CodeServiceInterface::class => CodeService::class,
        NotificationServiceInterface::class => NotificationService::class,
        ListViewServiceInterface::class => ListViewService::class,
        TaskHandlerRegistry::class => TaskHandlerRegistry::class,
    ];

    public function register(): void
    {
        $this->app->alias(AuditLogServiceInterface::class, AuditRecorder::class);
        $this->app->alias(BackgroundTaskServiceInterface::class, TaskDispatcher::class);
        $this->commands([PurgeTaskResults::class]);
        $this->app->bind(FileScanner::class, fn () => config('attachments.scanner') === 'clamav' ? new ClamAvScanner : new NullScanner);
    }

    public function boot(BulkActionRegistry $bulk, TaskHandlerRegistry $handlers): void
    {
        // Public morph aliases (attachments, notifications, tasks and audit store these, never class names).
        Relation::morphMap((array) config('audit.models'));

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Auth::provider(PersonEmailUserProvider::DRIVER, fn (Application $app) => new PersonEmailUserProvider($app->make(Hasher::class), User::class));

        // Security doc §2: ≥ 10 chars, mixed case, digit, symbol, not breached (HIBP lookup in production only).
        Password::defaults(fn () => Password::min(10)->mixedCase()->numbers()->symbols()
            ->when($this->app->isProduction(), fn (Password $rule) => $rule->uncompromised()));

        Event::listen(AuditableEvent::class, RecordAuditableEvent::class);
        Event::listen(UserCreated::class, SendAccountInvitation::class);
        Event::listen(UserLoggedOut::class, ForgetDevicesOnLogoutAll::class);
        Event::listen([TaskCompleted::class, TaskCancelled::class], NotifyTaskOwner::class);
        Event::listen(AttachmentUploaded::class, QueueAttachmentScan::class);

        $handlers->register(TaskType::BulkAction, BulkActionTaskHandler::class);
        $handlers->register(TaskType::AttachmentsZip, AttachmentsZipTaskHandler::class);
        $handlers->register(TaskType::AuditExport, AuditExportTaskHandler::class);

        // List resources (GET /meta/resources/{resource}); only roles have bulk actions.
        $bulk->register('roles', Role::class, Role::listQueryConfig(), RoleService::class);
        $bulk->register('users', User::class, User::listQueryConfig());
        $bulk->register('people', Person::class, Person::listQueryConfig(), genericActions: false);
        $bulk->register('audit', AuditLog::class, AuditLog::listQueryConfig());
        // Personal lists: every user sees their own tasks / views.
        $bulk->register('background_tasks', BackgroundTask::class, BackgroundTask::listQueryConfig())->readableBy(PermissionExpression::AUTHENTICATED);
        $bulk->register('attachments', Attachment::class, Attachment::listQueryConfig(), AttachmentService::class);
        $bulk->register('list_views', SavedView::class, SavedView::listQueryConfig())->readableBy(PermissionExpression::AUTHENTICATED);
        $bulk->register('notifications', Notification::class, Notification::listQueryConfig())
            ->action('resend', 'notifications.write', [NotificationServiceInterface::class, 'resend'])
            ->action('cancel', 'notifications.write', [NotificationServiceInterface::class, 'cancel']);
    }
}
