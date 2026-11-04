<?php

declare(strict_types=1);

namespace App\Listeners\Payments;

use App\Events\Documents\WrittenDocumentIssued;
use App\Interfaces\Payments\InvoiceServiceInterface;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * A charged document (medical report, fitness certificate …) goes on the patient's draft invoice when issued.
 * Free kinds charge nothing; a failure is reported and never undoes the document.
 */
final class ChargeDocumentFee implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly InvoiceServiceInterface $invoices) {}

    public function handle(WrittenDocumentIssued $event): void
    {
        rescue(fn () => $this->invoices->charge($event->document), report: true);
    }
}
