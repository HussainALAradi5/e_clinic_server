<?php

declare(strict_types=1);

namespace App\Listeners\Payments;

use App\Enums\Shared\PaymentMethod;
use App\Events\Payments\PaymentReceived;
use App\Interfaces\Identity\NotificationServiceInterface;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Every payment the patient makes (desk, balance, online) sends them a receipt notice that opens their invoices,
 * where the receipt can be printed or saved. The insurer's own payments are not the patient's and send none.
 */
final class SendPaymentReceipt implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly NotificationServiceInterface $notifications) {}

    public function handle(PaymentReceived $event): void
    {
        $payment = $event->payment->loadMissing(['invoice', 'patient.person']);
        if ($payment->method === PaymentMethod::Insurance) {
            return;
        }
        rescue(fn () => $this->notifications->send('payment.received', $payment->patient->person, $payment, [
            'code' => $payment->code,
            'amount' => MoneyHelper::format($payment->amount),
            'invoice' => (string) ($payment->invoice->invoice_number ?? $payment->invoice->code),
            'method' => $payment->method->label(),
            'balance' => MoneyHelper::format($payment->invoice->refresh()->balance_due),
        ]), report: true);
    }
}
