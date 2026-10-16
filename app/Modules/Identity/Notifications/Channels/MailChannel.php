<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications\Channels;

use App\Modules\Identity\Notifications\Contracts\NotificationChannelDriver;
use App\Modules\Identity\Notifications\Models\Notification;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

/**
 * Email through the configured Laravel mailer (plain text — minimum PHI).
 */
final class MailChannel implements NotificationChannelDriver
{
    public function send(Notification $notification): ?string
    {
        $sent = Mail::raw($notification->body, function (Message $message) use ($notification): void {
            $message->to((string) $notification->destination)->subject((string) ($notification->subject ?? config('app.name')));
        });

        return $sent?->getMessageId();
    }

    public function deliversImmediately(): bool
    {
        return false;
    }
}
