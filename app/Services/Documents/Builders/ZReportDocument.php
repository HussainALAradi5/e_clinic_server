<?php

declare(strict_types=1);

namespace App\Services\Documents\Builders;

use App\Dtos\Documents\Blocks\FactsBlock;
use App\Dtos\Documents\Blocks\SignatureBlock;
use App\Dtos\Documents\Blocks\TableBlock;
use App\Dtos\Documents\Blocks\TotalsBlock;
use App\Dtos\Documents\DocumentFact;
use App\Dtos\Documents\DocumentPage;
use App\Enums\Documents\DocumentTone;
use App\Enums\Shared\PaymentMethod;
use App\Interfaces\Documents\DocumentBuilder;
use App\Interfaces\Transactions\CashSessionServiceInterface;
use App\Models\Transactions\CashSession;
use App\Utilities\Documents\DocumentFormat;
use Illuminate\Database\Eloquent\Model;

/**
 * Z-report of a cash drawer shift: takings by payment method, how expected cash is built, the count, the cashier's
 * signature.
 */
final class ZReportDocument implements DocumentBuilder
{
    public function __construct(
        private readonly DocumentFormat $format,
        private readonly CashSessionServiceInterface $sessions,
    ) {}

    public function with(): array
    {
        return ['cashier.person', 'approver.person'];
    }

    public function build(Model $record): DocumentPage
    {
        /** @var CashSession $session */
        $session = $record;
        $f = $this->format;
        $report = $this->sessions->report($session);
        $cashier = $session->cashier->person->full_name;
        $methods = [];
        foreach ($report->byMethod as $method => $total) {
            $methods[] = [PaymentMethod::tryFrom($method)?->label() ?? $method, $f->money($total)];
        }

        return new DocumentPage(
            title: $f->text('z_report.title'),
            reference: $session->code,
            fileName: $f->fileName($f->text('z_report.title'), $session->code),
            badges: [$f->badge($session->status)],
            highlights: [
                new DocumentFact($f->text('z_report.cashier'), $cashier, tone: DocumentTone::Violet),
                new DocumentFact($f->text('z_report.opened'), $f->dateTime($session->opened_at), tone: DocumentTone::Info),
                new DocumentFact($f->text('z_report.closed'), $f->dateTime($session->closed_at), tone: DocumentTone::Warning),
                new DocumentFact($f->text('z_report.expected'), $f->money($report->expected), tone: DocumentTone::Success),
            ],
            blocks: [
                new TableBlock($f->text('z_report.takings'), $f->columns('z_report.columns'), $methods, numeric: [1], empty: $f->text('z_report.no_takings')),
                new FactsBlock($f->text('z_report.documents'), [
                    new DocumentFact($f->text('z_report.payments'), (string) $report->payments),
                    new DocumentFact($f->text('z_report.refunds'), (string) $report->refunds),
                    new DocumentFact($f->text('z_report.expenses'), (string) $report->expenses),
                    DocumentFact::optional($f->text('z_report.approved_by'), $session->approver?->person->full_name),
                ]),
                new TotalsBlock([
                    new DocumentFact($f->text('z_report.opening_float'), $f->money($report->openingFloat)),
                    new DocumentFact($f->text('z_report.cash_in'), '+ '.$f->money($report->cashIn)),
                    new DocumentFact($f->text('z_report.balance_deposits'), '+ '.$f->money($report->balanceDeposits)),
                    new DocumentFact($f->text('z_report.cash_refunds'), '− '.$f->money($report->cashRefunds)),
                    new DocumentFact($f->text('z_report.cash_expenses'), '− '.$f->money($report->cashExpenses)),
                    new DocumentFact($f->text('z_report.balance_paybacks'), '− '.$f->money($report->balancePaybacks)),
                    new DocumentFact($f->text('z_report.expected'), $f->money($report->expected)),
                    DocumentFact::optional($f->text('z_report.counted'), $session->counted_closing ? $f->money($session->counted_closing) : null),
                ], new DocumentFact($f->text('z_report.difference'), $f->money($session->difference))),
                new SignatureBlock($cashier, $f->text('z_report.cashier'), $session->cashier_id, stamp: false),
            ],
            verifyNote: $f->text('z_report.verify'),
            issuedBy: $cashier,
        );
    }
}
