<?php

declare(strict_types=1);

namespace App\Interfaces\Payments;

use App\Models\Payments\Invoice;

/**
 * Reminders for what a patient still owes on an issued invoice — their own part only (never the insurer's share).
 * Automatic: first `payments.reminders.first_after_days` after issue, then every `every_days`, at most
 * `max_per_invoice`. By hand: staff may send one more, but not twice within `min_hours_between`.
 */
interface PaymentReminderServiceInterface
{
    /** Sends the reminders due today; returns how many went out. */
    public function sendDue(): int;

    /** Sends one now (staff); refused when the patient owes nothing or one went out too recently. */
    public function remind(Invoice $invoice): Invoice;
}
