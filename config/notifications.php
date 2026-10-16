<?php

use App\Modules\Identity\Notifications\Channels\InAppChannel;
use App\Modules\Identity\Notifications\Channels\LogChannel;
use App\Modules\Identity\Notifications\Channels\MailChannel;

return [

    /*
    |--------------------------------------------------------------------------
    | Notifications (Identity › Notifications)
    |--------------------------------------------------------------------------
    | Every message goes through NotificationService::send(); modules never call providers directly.
    */

    /* Channel => driver name. Swap `log` for a real SMS / WhatsApp / FCM driver in production. */
    'channels' => [
        'sms' => env('NOTIFICATIONS_SMS_DRIVER', 'log'),
        'whatsapp' => env('NOTIFICATIONS_WHATSAPP_DRIVER', 'log'),
        'push' => env('NOTIFICATIONS_PUSH_DRIVER', 'log'),
        'email' => env('NOTIFICATIONS_EMAIL_DRIVER', 'mail'),
        'in_app' => 'in_app',
    ],

    'drivers' => [
        'log' => LogChannel::class,
        'mail' => MailChannel::class,
        'in_app' => InAppChannel::class,
    ],

    /* Log channel of the `log` driver (null = default). */
    'log_channel' => env('NOTIFICATIONS_LOG_CHANNEL'),

    'queue' => env('NOTIFICATIONS_QUEUE'),

    /* Minutes before the 2nd and 3rd attempt. */
    'backoff_minutes' => [1, 5, 15],

    /* Template => default channels. Texts live in lang/{en,ar}/notifications.php. */
    'templates' => [
        'task.completed' => ['channels' => ['in_app']],
        'task.failed' => ['channels' => ['in_app']],
    ],

    /* Security messages (OTP, password reset) ignore the person's opt-outs. */
    'security_templates' => [],

    /* Delivery webhooks: provider => HMAC secret of the raw body (header X-Signature). */
    'webhooks' => [
        'log' => ['secret' => env('NOTIFICATIONS_WEBHOOK_SECRET')],
    ],

];
