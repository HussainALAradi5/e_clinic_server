<?php

declare(strict_types=1);

namespace App\Listeners\Payments;

use App\Events\Appointments\AppointmentCompleted;
use App\Events\Appointments\ClinicalOrderCompleted;
use App\Exceptions\Payments\AlreadyChargedException;
use App\Interfaces\Payments\InvoiceServiceInterface;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Throwable;

/**
 * Completed visits, treatment sessions and clinical orders are charged onto the patient's open draft invoice
 * (the single billing path); a source already on an invoice is skipped.
 */
final class ChargeCompletedWork implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly InvoiceServiceInterface $invoices) {}

    public function handle(AppointmentCompleted|ClinicalOrderCompleted $event): void
    {
        try {
            $this->invoices->charge($event instanceof AppointmentCompleted ? $event->appointment : $event->order);
        } catch (AlreadyChargedException) {
            // Idempotent: billed before (e.g. by hand).
        } catch (Throwable $e) {
            // Billing never undoes the clinical step; reception can still bill it by hand.
            report($e);
        }
    }
}
