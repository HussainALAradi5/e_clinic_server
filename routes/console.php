<?php

use App\Enums\HR\EmployeeStatus;
use App\Interfaces\Appointments\AppointmentServiceInterface;
use App\Interfaces\HR\AttendanceServiceInterface;
use App\Interfaces\HR\LeaveServiceInterface;
use App\Interfaces\Pharmacy\InventoryServiceInterface;
use App\Models\HR\Employee;
use App\Models\Identity\UploadSession;
use App\Notifications\Identity\Contracts\NotificationServiceInterface;
use App\Notifications\Identity\Models\DeviceToken;
use App\Notifications\Identity\Models\Notification;
use App\Services\Pharmacy\StockAlertNotifier;
use App\Tasks\Identity\Models\BackgroundTask;
use App\Utilities\Shared\ClinicClock;
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

// Appointments: confirmed bookings not checked in 15 minutes after the start become no-shows.
Schedule::call(fn () => app(AppointmentServiceInterface::class)->markDueNoShows())
    ->name('appointments:mark-no-shows')->everyFiveMinutes()->withoutOverlapping();

// Pharmacy: expired lots are written off just after midnight; stock alerts reach the pharmacists in the morning.
Schedule::call(fn () => app(InventoryServiceInterface::class)->writeOffExpired(app(ClinicClock::class)->today()))
    ->name('pharmacy:write-off-expired')->dailyAt('00:20')->timezone(app(ClinicClock::class)->timezone())->withoutOverlapping();
Schedule::call(fn () => app(StockAlertNotifier::class)())
    ->name('pharmacy:stock-alerts')->dailyAt('07:00')->timezone(app(ClinicClock::class)->timezone())->withoutOverlapping();

// HR: the day's attendance is closed before midnight (weekends, holidays, leave, absences, missing check-outs);
// leave balances of the new year open on 1 January (carry-forward from the old year).
Schedule::call(fn () => app(AttendanceServiceInterface::class)->closeDay(app(ClinicClock::class)->today()))
    ->name('hr:close-attendance-day')->dailyAt('23:55')->timezone(app(ClinicClock::class)->timezone())->withoutOverlapping();
Schedule::call(function (): void {
    $leaves = app(LeaveServiceInterface::class);
    $year = app(ClinicClock::class)->today()->year;
    Employee::query()->where('status', EmployeeStatus::Active->value)->each(fn (Employee $e) => $leaves->initializeBalances($e, $year));
})->name('hr:roll-leave-balances')->yearlyOn(1, 1, '00:30')->timezone(app(ClinicClock::class)->timezone())->withoutOverlapping();
