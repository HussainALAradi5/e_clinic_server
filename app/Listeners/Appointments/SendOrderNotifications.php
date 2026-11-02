<?php

declare(strict_types=1);

namespace App\Listeners\Appointments;

use App\Events\Appointments\ClinicalOrderCompleted;
use App\Notifications\Identity\Contracts\NotificationServiceInterface;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * A result is ready: the ordering doctor is told in the app (abnormal results with their own, louder message);
 * the patient only hears that a result is ready — never the finding.
 */
final class SendOrderNotifications implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly NotificationServiceInterface $notifications) {}

    public function handle(ClinicalOrderCompleted $event): void
    {
        $order = $event->order->loadMissing(['patient.person', 'orderedBy.person']);
        if ($order->isReferral()) {
            return;
        }
        $data = ['code' => $order->code, 'patient' => $order->patient->person->full_name, 'type' => $order->type->label()];

        $this->notifications->send($order->is_abnormal === true ? 'order.abnormal' : 'order.result_ready', $order->orderedBy->person, $order, $data);
        $this->notifications->send('order.patient_result_ready', $order->patient->person, $order, ['code' => $order->code]);
    }
}
