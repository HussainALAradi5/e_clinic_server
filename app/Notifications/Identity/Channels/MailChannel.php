<?php

declare(strict_types=1);

namespace App\Notifications\Identity\Channels;

use App\Notifications\Identity\Contracts\NotificationChannelDriver;
use App\Notifications\Identity\Models\Notification;
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
