<?php

declare(strict_types=1);

namespace App\Listeners\Payments;

use App\Events\Payments\RefundApproved;
use App\Events\Payments\RefundRejected;
use App\Events\Payments\RefundRequested;
use App\Models\Identity\Person;
use App\Notifications\Identity\Contracts\NotificationServiceInterface;
use App\Utilities\Shared\MoneyHelper;
use App\Utilities\Shared\Recipients;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Refund workflow in the app: a request reaches the approvers; the decision reaches the requester, and an approved
 * refund also reaches whoever pays it out.
 */
final class SendRefundNotifications implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly NotificationServiceInterface $notifications) {}

    public function handle(RefundRequested|RefundApproved|RefundRejected $event): void
    {
        $refund = $event->refund->loadMissing('invoice.patient.person');
        $data = ['code' => $refund->code, 'amount' => MoneyHelper::format($refund->amount), 'patient' => $refund->invoice->patient->person->full_name];

        [$template, $people] = match (true) {
            $event instanceof RefundRequested => ['refund.requested', Recipients::withPermission('refunds.approve', [$refund->requested_by])],
            $event instanceof RefundApproved => ['refund.approved', Recipients::user($refund->requested_by)
                ->merge(Recipients::withPermission('refunds.process', [$refund->requested_by, $refund->approved_by]))],
            default => ['refund.rejected', Recipients::user($refund->requested_by)],
        };

        $people->unique('id')->each(fn (Person $p) => $this->notifications->send($template, $p, $refund, [...$data, 'reason' => (string) $refund->rejection_reason]));
    }
}
