<?php

declare(strict_types=1);

namespace App\Services\Documents\Builders;

use App\Dtos\Documents\Blocks\FactsBlock;
use App\Dtos\Documents\Blocks\NoticeBlock;
use App\Dtos\Documents\Blocks\TableBlock;
use App\Dtos\Documents\Blocks\TextBlock;
use App\Dtos\Documents\Blocks\TotalsBlock;
use App\Dtos\Documents\DocumentFact;
use App\Dtos\Documents\DocumentPage;
use App\Enums\Documents\DocumentTone;
use App\Enums\Payments\PaymentStatus;
use App\Interfaces\Documents\DocumentBuilder;
use App\Models\Payments\Invoice;
use App\Models\Payments\InvoiceItem;
use App\Models\Payments\Payment;
use App\Utilities\Documents\DocumentFormat;
use App\Utilities\Shared\ClinicClock;
use Illuminate\Database\Eloquent\Model;

/**
 * Tax invoice: lines with discount and VAT, totals, payments received and what is left to pay. The badge is the
 * payment state (unpaid, partially paid, paid, overdue…).
 */
final class InvoiceDocument implements DocumentBuilder
{
    public function __construct(
        private readonly DocumentFormat $format,
        private readonly ClinicClock $clock,
    ) {}

    public function with(): array
    {
        return ['patient.person', 'items', 'payments', 'issuer.person'];
    }

    public function build(Model $record): DocumentPage
    {
        /** @var Invoice $invoice */
        $invoice = $record;
        $f = $this->format;
        $number = $invoice->invoice_number ?? $invoice->code;
        $payments = $invoice->payments->where('status', PaymentStatus::Completed);

        return new DocumentPage(
            title: $f->text('invoice.title'),
            reference: $number,
            fileName: $f->fileName($f->text('invoice.file'), $number),
            badges: [$f->badge($invoice->paymentState($this->clock->today()))],
            highlights: [
                new DocumentFact($f->text('invoice.issued'), $f->date($invoice->issue_date), tone: DocumentTone::Info),
                new DocumentFact($f->text('invoice.due'), $f->date($invoice->due_date), tone: DocumentTone::Warning),
                new DocumentFact($f->text('invoice.total'), $f->money($invoice->total), tone: DocumentTone::Violet),
                new DocumentFact($f->text('invoice.balance'), $f->money($invoice->balance_due), tone: $invoice->balance_due->isZero() ? DocumentTone::Success : DocumentTone::Danger),
            ],
            blocks: [
                ...($invoice->cancelled_at !== null ? [new NoticeBlock($f->text('common.cancelled_on', ['at' => $f->dateTime($invoice->cancelled_at)]), (string) $invoice->cancellation_reason, DocumentTone::Danger)] : []),
                new FactsBlock($f->text('invoice.bill_to'), $f->patient($invoice->patient, withCpr: true)),
                new TableBlock($f->text('invoice.services'), $f->columns('invoice.columns'), $invoice->items->map(fn (InvoiceItem $i): array => [
                    $i->description, $i->quantity, $f->money($i->unit_price), $f->money($i->discount_amount), $f->money($i->tax_amount), $f->money($i->line_total),
                ])->all(), numeric: [1, 2, 3, 4, 5]),
                new TotalsBlock([
                    new DocumentFact($f->text('invoice.subtotal'), $f->money($invoice->subtotal)),
                    new DocumentFact($f->text('invoice.discount'), '− '.$f->money($invoice->discount_total)),
                    new DocumentFact($f->text('invoice.vat'), $f->money($invoice->tax_total)),
                    new DocumentFact($f->text('invoice.total'), $f->money($invoice->total)),
                    new DocumentFact($f->text('invoice.paid'), '− '.$f->money($invoice->paid_amount)),
                    $invoice->refunded_amount->isZero() ? null : new DocumentFact($f->text('invoice.refunded'), $f->money($invoice->refunded_amount)),
                ], new DocumentFact($f->text('invoice.balance'), $f->money($invoice->balance_due))),
                new TableBlock($f->text('invoice.payments'), $f->columns('invoice.payment_columns'), $payments->map(fn (Payment $p): array => [
                    $p->code, $f->dateTime($p->paid_at), $p->method->label(), $f->money($p->amount),
                ])->values()->all(), numeric: [3], empty: $f->text('invoice.no_payments')),
                new TextBlock($f->text('common.notes'), [$invoice->notes]),
            ],
            verifyNote: $f->text('invoice.verify'),
            issuedBy: $invoice->issuer?->person->full_name,
        );
    }
}
