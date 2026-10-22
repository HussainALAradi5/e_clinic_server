<?php

declare(strict_types=1);

namespace App\Modules\Payments\Refunds\Listeners;

use App\Modules\Identity\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Payments\Refunds\Events\RefundApproved;
use App\Modules\Payments\Refunds\Events\RefundRejected;
use App\Modules\Payments\Refunds\Events\RefundRequested;
use App\Shared\Support\MoneyHelper;
use App\Shared\Support\Recipients;
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
