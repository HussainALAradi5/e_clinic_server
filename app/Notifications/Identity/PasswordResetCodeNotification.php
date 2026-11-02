<?php

declare(strict_types=1);

namespace App\Notifications\Identity;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

/**
 * Emails the 6-digit code of a "forgot password" request (typed on the reset page, never a link).
 */
final class PasswordResetCodeNotification extends Notification
{
    use Queueable;

    public function __construct(#[\SensitiveParameter] private readonly string $code) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your password reset code: :code', ['code' => $this->code]))
            ->line(__('Enter this code on the reset page to choose a new password:'))
            ->line('**'.$this->code.'**')
            ->line(__('The code expires in :count minutes. If you did not ask for it, ignore this email — your password stays the same.', [
                'count' => Config::integer('clinic.auth.password_code_minutes'),
            ]));
    }
}
