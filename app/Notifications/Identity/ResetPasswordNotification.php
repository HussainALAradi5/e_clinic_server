<?php

declare(strict_types=1);

namespace App\Notifications\Identity;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

/**
 * Emails the Angular link `{CLIENT_URL}/reset-password?token=…&email=…` — a password reset,
 * or the "set your password" invitation of a new account (same token flow).
 */
final class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        #[\SensitiveParameter] private readonly string $token,
        private readonly string $email,
        private readonly bool $invitation = false,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(): string
    {
        return rtrim(Config::string('clinic.client_url'), '/').'/reset-password?'.http_build_query(['token' => $this->token, 'email' => $this->email]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = Config::integer('auth.passwords.users.expire');

        return $this->invitation
            ? (new MailMessage)
                ->subject(__('Your :app account', ['app' => config('app.name')]))
                ->line(__('An account was created for you. Choose a password to sign in.'))
                ->action(__('Set password'), $this->url())
                ->line(__('This link expires in :count minutes. Ask an administrator for a new one if it has expired.', ['count' => $minutes]))
            : (new MailMessage)
                ->subject(__('Reset your password'))
                ->line(__('We received a request to reset the password of your account.'))
                ->action(__('Reset password'), $this->url())
                ->line(__('This link expires in :count minutes. If you did not ask for it, ignore this email.', ['count' => $minutes]));
    }
}
