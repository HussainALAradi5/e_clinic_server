<?php

declare(strict_types=1);

namespace App\Jobs\Identity;

use App\Interfaces\Identity\WebPushServiceInterface;
use App\Models\Identity\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Pushes one in-app message to the browsers of its user (a missed push is not retried: the inbox still has it).
 */
final class SendWebPush implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $notificationId)
    {
        $this->onQueue(config('notifications.queue'));
    }

    public function handle(WebPushServiceInterface $push): void
    {
        $notification = Notification::query()->with('person', 'user')->find($this->notificationId);
        if ($notification !== null) {
            $push->send($notification);
        }
    }
}
