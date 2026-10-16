<?php

use App\Modules\Identity\Attachments\Models\UploadSession;
use App\Modules\Identity\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Identity\Notifications\Models\DeviceToken;
use App\Modules\Identity\Notifications\Models\Notification;
use App\Modules\Identity\Tasks\Models\BackgroundTask;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduler (`php artisan schedule:work` locally, cron `schedule:run` in production)
|--------------------------------------------------------------------------
*/

// Notifications: send due reminders / retries every minute.
Schedule::call(fn () => app(NotificationServiceInterface::class)->dispatchDue())
    ->name('notifications:dispatch-due')->everyMinute()->withoutOverlapping();

// Background tasks: delete expired result files.
Schedule::command('tasks:purge-results')->dailyAt('02:10');

// Retention: finished tasks (90 d), notifications (in-app 6 m / 2 y), stale device tokens (60 d), upload sessions.
Schedule::command('model:prune', ['--model' => [BackgroundTask::class, Notification::class, DeviceToken::class, UploadSession::class]])
    ->dailyAt('02:30');
