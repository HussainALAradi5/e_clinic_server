<?php

declare(strict_types=1);

namespace App\Services\Documents\Builders;

use App\Dtos\Documents\Blocks\FactsBlock;
use App\Dtos\Documents\Blocks\NoticeBlock;
use App\Dtos\Documents\Blocks\TotalsBlock;
use App\Dtos\Documents\DocumentFact;
use App\Dtos\Documents\DocumentPage;
use App\Enums\Documents\DocumentTone;
use App\Interfaces\Documents\DocumentBuilder;
use App\Models\Payments\Payment;
use App\Utilities\Documents\DocumentFormat;
use Illuminate\Database\Eloquent\Model;

/**
 * Payment receipt: amount, method, the invoice it pays and what is left on it.
 */
final class ReceiptDocument implements DocumentBuilder
{
    public function __construct(private readonly DocumentFormat $format) {}

    public function with(): array
    {
        return ['patient.person', 'invoice', 'receiver.person'];
    }

    public function build(Model $record): DocumentPage
    {
        /** @var Payment $payment */
        $payment = $record;
        $f = $this->format;
        $invoice = $payment->invoice;

        return new DocumentPage(
            title: $f->text('receipt.title'),
            reference: $payment->code,
            fileName: $f->fileName($f->text('receipt.file'), $payment->code),
            badges: [$f->badge($payment->status), $f->badge($payment->method)],
            highlights: [
                new DocumentFact($f->text('receipt.paid'), $f->dateTime($payment->paid_at), tone: DocumentTone::Info),
                new DocumentFact($f->text('receipt.amount'), $f->money($payment->amount), tone: DocumentTone::Success),
                new DocumentFact($f->text('receipt.method'), $payment->method->label().($payment->card_last4 ? ' ·· '.$payment->card_last4 : ''), tone: DocumentTone::Violet),
                new DocumentFact($f->text('receipt.invoice'), $invoice->invoice_number ?? $invoice->code, tone: DocumentTone::Warning),
            ],
            blocks: [
                ...($payment->voided_at !== null ? [new NoticeBlock($f->text('receipt.voided_on', ['at' => $f->dateTime($payment->voided_at)]), (string) $payment->void_reason, DocumentTone::Danger)] : []),
                new FactsBlock($f->text('receipt.received_from'), $f->patient($payment->patient)),
                new FactsBlock($f->text('receipt.payment'), [
                    new DocumentFact($f->text('receipt.received_by'), $payment->receiver->person->full_name),
                    DocumentFact::optional($f->text('receipt.reference'), $payment->reference_number),
                    DocumentFact::optional($f->text('common.notes'), $payment->notes, wide: true),
                ]),
                new TotalsBlock([
                    new DocumentFact($f->text('receipt.invoice_total'), $f->money($invoice->total)),
                    new DocumentFact($f->text('receipt.this_payment'), $f->money($payment->amount)),
                ], new DocumentFact($f->text('receipt.left'), $f->money($invoice->balance_due))),
            ],
            verifyNote: $f->text('receipt.verify'),
            issuedBy: $payment->receiver->person->full_name,
        );
    }
}
