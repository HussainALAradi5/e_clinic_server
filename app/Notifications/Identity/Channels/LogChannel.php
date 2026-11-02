<?php

declare(strict_types=1);

namespace App\Notifications\Identity\Channels;

use App\Notifications\Identity\Contracts\NotificationChannelDriver;
use App\Notifications\Identity\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Development driver: writes the message to the log instead of calling a provider (SMS, WhatsApp, push).
 */
final class LogChannel implements NotificationChannelDriver
{
    public function send(Notification $notification): string
    {
        Log::channel(config('notifications.log_channel'))->info('Notification', [
            'channel' => $notification->channel->value,
            'template' => $notification->template,
            'destination' => $notification->destination,
            'body' => $notification->body,
        ]);

        return 'log-'.Str::uuid7()->toString();
    }

    public function deliversImmediately(): bool
    {
        return false;
    }
}
