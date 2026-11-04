<?php

declare(strict_types=1);

namespace App\Listeners\Payments;

use App\Events\Payments\InvoiceCancelled;
use App\Events\Payments\PaymentReceived;
use App\Interfaces\Payments\PackageCoverServiceInterface;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Packages follow the invoice that sold them: fully paid → active (from today, for its validity); cancelled
 * before payment → cancelled.
 */
final class UpdatePackagesFromInvoice implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly PackageCoverServiceInterface $packages) {}

    public function handle(PaymentReceived|InvoiceCancelled $event): void
    {
        if ($event instanceof PaymentReceived) {
            $this->packages->activatePaid($event->payment->invoice()->firstOrFail());

            return;
        }
        $this->packages->cancelUnpaid($event->invoice);
    }
}
