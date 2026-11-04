<?php

declare(strict_types=1);

namespace App\Services\Documents\Builders;

use App\Dtos\Documents\Blocks\FactsBlock;
use App\Dtos\Documents\Blocks\NoticeBlock;
use App\Dtos\Documents\Blocks\TableBlock;
use App\Dtos\Documents\Blocks\TotalsBlock;
use App\Dtos\Documents\DocumentFact;
use App\Dtos\Documents\DocumentPage;
use App\Enums\Documents\DocumentTone;
use App\Interfaces\Documents\DocumentBuilder;
use App\Models\Payments\InsuranceClaim;
use App\Models\Payments\InvoiceItem;
use App\Utilities\Documents\DocumentFormat;
use Illuminate\Database\Eloquent\Model;

/**
 * Insurance claim form for the insurer: member and policy, the invoiced services and the amount claimed (and approved).
 */
final class ClaimDocument implements DocumentBuilder
{
    public function __construct(private readonly DocumentFormat $format) {}

    public function with(): array
    {
        return ['patient.person', 'insurer', 'invoice.items'];
    }

    public function build(Model $record): DocumentPage
    {
        /** @var InsuranceClaim $claim */
        $claim = $record;
        $f = $this->format;
        $invoice = $claim->invoice;

        return new DocumentPage(
            title: $f->text('claim.title'),
            reference: $claim->code,
            fileName: $f->fileName($f->text('claim.title'), $claim->code),
            badges: [$f->badge($claim->status)],
            highlights: [
                new DocumentFact($f->text('claim.insurer'), $claim->insurer->name, tone: DocumentTone::Info),
                new DocumentFact($f->text('claim.coverage'), rtrim(rtrim($claim->coverage_percent, '0'), '.').'%', tone: DocumentTone::Violet),
                new DocumentFact($f->text('claim.claimed'), $f->money($claim->claimed_amount), tone: DocumentTone::Warning),
                $claim->approved_amount ? new DocumentFact($f->text('claim.approved'), $f->money($claim->approved_amount), tone: DocumentTone::Success) : null,
            ],
            blocks: [
                ...($claim->rejection_reason ? [new NoticeBlock($f->text('claim.rejected'), $claim->rejection_reason, DocumentTone::Danger)] : []),
                new FactsBlock($f->text('claim.member'), [
                    ...$f->patient($claim->patient, withCpr: true),
                    new DocumentFact($f->text('claim.policy'), $claim->policy_number),
                    DocumentFact::optional($f->text('claim.pre_auth'), $claim->pre_authorization_number),
                ]),
                new TableBlock($f->text('claim.services', ['number' => $invoice->invoice_number ?? $invoice->code]), $f->columns('claim.columns'), $invoice->items->map(fn (InvoiceItem $i): array => [
                    $i->description, $i->quantity, $f->money($i->line_total),
                ])->all(), numeric: [1, 2]),
                new TotalsBlock([
                    new DocumentFact($f->text('claim.invoice_total'), $f->money($invoice->total)),
                    DocumentFact::optional($f->text('claim.submitted'), $claim->submitted_at ? $f->dateTime($claim->submitted_at) : null),
                ], new DocumentFact($f->text('claim.amount'), $f->money($claim->claimed_amount))),
            ],
            verifyNote: $f->text('claim.verify'),
        );
    }
}
