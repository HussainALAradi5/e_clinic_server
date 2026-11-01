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

    /* Template => default channels. Texts live in lang/{en,ar}/notifications.php. Patient messages also go to the
       in-app inbox, which the portal shows. */
    'templates' => [
        'task.completed' => ['channels' => ['in_app']],
        'patient.emergency_access' => ['channels' => ['in_app', 'email']],
        'ticket.opened' => ['channels' => ['in_app']],
        'chat.added' => ['channels' => ['in_app']],
        'chat.removed' => ['channels' => ['in_app']],
        'chat.role' => ['channels' => ['in_app']],
        'chat.mention' => ['channels' => ['in_app']],
        'ticket.updated' => ['channels' => ['in_app']],
        'ticket.resolved' => ['channels' => ['in_app', 'email']],
        'ticket.replied' => ['channels' => ['in_app']],
        'ticket.confirmed' => ['channels' => ['in_app']],
        'ticket.declined' => ['channels' => ['in_app']],
        'task.failed' => ['channels' => ['in_app']],
        'appointment.booked' => ['channels' => ['in_app', 'sms', 'email']],
        'appointment.rescheduled' => ['channels' => ['in_app', 'sms', 'email']],
        'appointment.reminder' => ['channels' => ['in_app', 'sms', 'whatsapp']],
        'appointment.cancelled' => ['channels' => ['in_app', 'sms', 'email']],
        'treatment.urgent' => ['channels' => ['in_app']],
        'treatment.attendance_alert' => ['channels' => ['in_app']],
        'treatment.completed' => ['channels' => ['in_app', 'sms', 'email']],
        'order.result_ready' => ['channels' => ['in_app']],
        'order.abnormal' => ['channels' => ['in_app', 'push']],
        'order.patient_result_ready' => ['channels' => ['in_app', 'sms', 'email']],
        'refund.requested' => ['channels' => ['in_app']],
        'refund.approved' => ['channels' => ['in_app']],
        'refund.rejected' => ['channels' => ['in_app']],
        'expense.submitted' => ['channels' => ['in_app']],
        'expense.approved' => ['channels' => ['in_app']],
        'expense.rejected' => ['channels' => ['in_app']],
        'prescription.created' => ['channels' => ['in_app', 'sms']],
        'pharmacy.stock_alert' => ['channels' => ['in_app']],
        'leave.requested' => ['channels' => ['in_app']],
        'leave.approved' => ['channels' => ['in_app', 'email']],
        'leave.rejected' => ['channels' => ['in_app', 'email']],
        'payroll.calculated' => ['channels' => ['in_app']],
        'payroll.payslip_ready' => ['channels' => ['in_app', 'email']],
    ],

    /* Security messages (OTP, password reset) ignore the person's opt-outs. */
    'security_templates' => [],

    /* Delivery webhooks: provider => HMAC secret of the raw body (header X-Signature). */
    'webhooks' => [
        'log' => ['secret' => env('NOTIFICATIONS_WEBHOOK_SECRET')],
    ],

];
