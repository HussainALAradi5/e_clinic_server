<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Console\Commands\Identity\PurgeTaskResults;
use App\Enums\Identity\TaskType;
use App\Events\Identity\AttachmentUploaded;
use App\Events\Identity\TaskCancelled;
use App\Events\Identity\TaskCompleted;
use App\Events\Identity\UserCreated;
use App\Events\Identity\UserLoggedOut;
use App\Http\Resources\Identity\AttachmentResource;
use App\Http\Resources\Identity\AuditLogResource;
use App\Http\Resources\Identity\NotificationResource;
use App\Http\Resources\Identity\PersonResource;
use App\Http\Resources\Identity\RoleResource;
use App\Http\Resources\Identity\UserResource;
use App\Imports\Identity\PersonImport;
use App\Imports\Identity\RoleImport;
use App\Imports\Identity\UserImport;
use App\Interfaces\Documents\DocumentServiceInterface;
use App\Interfaces\Identity\AttachmentServiceInterface;
use App\Interfaces\Identity\AuditableEvent;
use App\Interfaces\Identity\AuditLogServiceInterface;
use App\Interfaces\Identity\AuthServiceInterface;
use App\Interfaces\Identity\BackgroundTaskServiceInterface;
use App\Interfaces\Identity\CodeServiceInterface;
use App\Interfaces\Identity\FileScanner;
use App\Interfaces\Identity\ListViewServiceInterface;
use App\Interfaces\Identity\NotificationServiceInterface;
use App\Interfaces\Identity\RoleServiceInterface;
use App\Interfaces\Identity\SignatureServiceInterface;
use App\Interfaces\Identity\UserServiceInterface;
use App\Interfaces\Identity\WebPushServiceInterface;
use App\Interfaces\Shared\AuditRecorder;
use App\Interfaces\Shared\TaskDispatcher;
use App\Listeners\Identity\ForgetDevicesOnLogoutAll;
use App\Listeners\Identity\NotifyTaskOwner;
use App\Listeners\Identity\QueueAttachmentScan;
use App\Listeners\Identity\RecordAuditableEvent;
use App\Listeners\Identity\SendAccountInvitation;
use App\Models\Identity\Attachment;
use App\Models\Identity\AuditLog;
use App\Models\Identity\BackgroundTask;
use App\Models\Identity\Notification;
use App\Models\Identity\Person;
use App\Models\Identity\PersonalAccessToken;
use App\Models\Identity\Role;
use App\Models\Identity\SavedView;
use App\Models\Identity\User;
use App\Scanners\Identity\ClamAvScanner;
use App\Scanners\Identity\NullScanner;
use App\Services\Bulk\BulkActionRegistry;
use App\Services\Documents\DocumentService;
use App\Services\Identity\AttachmentService;
use App\Services\Identity\AuditLogService;
use App\Services\Identity\AuthService;
use App\Services\Identity\BackgroundTaskService;
use App\Services\Identity\CodeService;
use App\Services\Identity\ListViewService;
use App\Services\Identity\NotificationService;
use App\Services\Identity\RoleService;
use App\Services\Identity\SignatureService;
use App\Services\Identity\UserService;
use App\Services\Identity\WebPushService;
use App\Tasks\Bulk\BulkActionTaskHandler;
use App\Tasks\Identity\AttachmentsZipTaskHandler;
use App\Tasks\Transfer\ListExportTaskHandler;
use App\Utilities\Access\PermissionExpression;
use App\Utilities\Identity\PersonEmailUserProvider;
use App\Utilities\Identity\TaskHandlerRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
        SignatureServiceInterface::class => SignatureService::class,
        DocumentServiceInterface::class => DocumentService::class,
        NotificationServiceInterface::class => NotificationService::class,
        WebPushServiceInterface::class => WebPushService::class,
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
        $handlers->register(TaskType::ListExport, ListExportTaskHandler::class);

        // List resources (GET /meta/resources/{resource}) with their bulk actions and export / import.
        $bulk->register('roles', Role::class, Role::listQueryConfig(), RoleService::class)
            ->exportable(RoleResource::class, fn (Builder $q) => $q->withCount(['users', 'permissions']))
            ->importable(RoleImport::class);
        $bulk->register('users', User::class, User::listQueryConfig())
            ->exportable(UserResource::class, fn (Builder $q) => $q->with(['person', 'roles'])->whereHas('person', fn (Builder $p) => $p->where('is_deleted', false)))
            ->importable(UserImport::class);
        $bulk->register('people', Person::class, Person::listQueryConfig(), genericActions: false)
            ->exportable(PersonResource::class, fn (Builder $q) => $q->with(['user', 'nationality']), values: [
                'account' => fn (Model $person): string => $person instanceof Person && $person->user !== null ? $person->user->user_type->label() : 'No account',
            ])
            ->importable(PersonImport::class);
        $bulk->register('audit', AuditLog::class, AuditLog::listQueryConfig())
            ->exportable(AuditLogResource::class, fn (Builder $q) => $q->with('user.person'), extraColumns: [
                'old_values' => 'Old values',
                'new_values' => 'New values',
                'ip_address' => 'IP address',
                'request_id' => 'Request id',
            ], values: [
                'auditable' => fn (Model $log): string => $log instanceof AuditLog ? trim(($log->auditableAlias() ?? (string) $log->auditable_type).' '.$log->auditable_id) : '',
                'user' => fn (Model $log): string => $log instanceof AuditLog ? ($log->user?->person->full_name ?? 'System') : '',
            ]);
        // Personal lists: every user sees their own tasks / views (not exportable).
        $bulk->register('background_tasks', BackgroundTask::class, BackgroundTask::listQueryConfig())->readableBy(PermissionExpression::AUTHENTICATED);
        $bulk->register('attachments', Attachment::class, Attachment::listQueryConfig(), AttachmentService::class)
            ->exportable(AttachmentResource::class);
        $bulk->register('list_views', SavedView::class, SavedView::listQueryConfig())->readableBy(PermissionExpression::AUTHENTICATED);
        $bulk->register('notifications', Notification::class, Notification::listQueryConfig())
            ->action('resend', 'notifications.write', [NotificationServiceInterface::class, 'resend'])
            ->action('cancel', 'notifications.write', [NotificationServiceInterface::class, 'cancel'])
            ->exportable(NotificationResource::class);
    }
}
