<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\Payments\InvoiceStatus;
use App\Exceptions\Payments\ReminderNotAllowedException;
use App\Interfaces\Identity\NotificationServiceInterface;
use App\Interfaces\Payments\InvoiceServiceInterface;
use App\Interfaces\Payments\PaymentReminderServiceInterface;
use App\Models\Payments\Invoice;
use App\Utilities\Payments\InsuranceCoverage;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\MoneyHelper;
use Brick\Money\Money;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

final class PaymentReminderService implements PaymentReminderServiceInterface
{
    public function __construct(
        private readonly NotificationServiceInterface $notifications,
        private readonly InvoiceServiceInterface $invoices,
        private readonly ClinicClock $clock,
    ) {}

    public function sendDue(): int
    {
        $today = $this->clock->today();
        $sent = 0;
        Invoice::query()
            ->whereIn('status', array_map(fn (InvoiceStatus $s): string => $s->value, array_filter(InvoiceStatus::cases(), fn (InvoiceStatus $s): bool => $s->isPayable())))
            ->where('balance_due', '>', 0)
            ->where('reminders_sent', '<', Config::integer('payments.reminders.max_per_invoice'))
            ->whereDate('issue_date', '<=', $today->subDays(Config::integer('payments.reminders.first_after_days')))
            ->where(fn ($q) => $q->whereNull('last_reminded_at')
                ->orWhere('last_reminded_at', '<=', $this->clock->now()->subDays(Config::integer('payments.reminders.every_days'))))
            ->orderBy('issue_date')
            ->chunkById(100, function ($invoices) use (&$sent): void {
                foreach ($invoices as $invoice) {
                    $owed = $this->owed($invoice);
                    if ($owed->isPositive() && rescue(fn (): bool => $this->send($invoice, $owed), false, report: true)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    public function remind(Invoice $invoice): Invoice
    {
        if (! $invoice->status->isPayable()) {
            throw new ReminderNotAllowedException(__('errors.REMINDER_NOTHING_OWED'));
        }
        $owed = $this->owed($invoice);
        if (! $owed->isPositive()) {
            throw new ReminderNotAllowedException(__('errors.REMINDER_NOTHING_OWED'));
        }
        $gap = Config::integer('payments.reminders.min_hours_between');
        if ($invoice->last_reminded_at !== null && $invoice->last_reminded_at->gt($this->clock->now()->subHours($gap))) {
            throw new ReminderNotAllowedException(__('errors.REMINDER_TOO_SOON', ['hours' => $gap]), ['last_reminded_at' => $invoice->last_reminded_at->toIso8601String()]);
        }
        $this->send($invoice, $owed);

        return $this->invoices->find($invoice->id);
    }

    /** The patient's own part still owed (never the insurer's share). */
    private function owed(Invoice $invoice): Money
    {
        $split = InsuranceCoverage::split($invoice, $this->clock->today());

        return $split->covered ? $split->patientDue : $invoice->balance_due;
    }

    private function send(Invoice $invoice, Money $owed): bool
    {
        $person = $invoice->patient->person;
        $this->notifications->send('payment.reminder', $person, $invoice, [
            'invoice' => (string) ($invoice->invoice_number ?? $invoice->code),
            'amount' => MoneyHelper::format($owed),
            'date' => $invoice->issue_date?->format('d M Y') ?? '',
        ]);
        DB::table('invoices')->where('id', $invoice->id)->update([
            'reminders_sent' => DB::raw('reminders_sent + 1'),
            'last_reminded_at' => $this->clock->now(),
        ]);

        return true;
    }
}
