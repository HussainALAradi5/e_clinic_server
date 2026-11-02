<?php

declare(strict_types=1);

namespace App\Notifications\Identity\Jobs;

use App\Notifications\Identity\Contracts\NotificationServiceInterface;
use App\Notifications\Identity\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Sends one notification that is due now (later ones are picked up by the every-minute dispatcher).
 */
final class DeliverNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $notificationId)
    {
        $this->onQueue(config('notifications.queue'));
    }

    public function handle(NotificationServiceInterface $notifications): void
    {
        $notification = Notification::query()->find($this->notificationId);
        if ($notification !== null) {
            $notifications->deliver($notification);
        }
    }
}
