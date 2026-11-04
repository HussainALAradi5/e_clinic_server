<?php

declare(strict_types=1);

namespace App\Services\Documents\Builders;

use App\Dtos\Documents\Blocks\FactsBlock;
use App\Dtos\Documents\Blocks\TableBlock;
use App\Dtos\Documents\Blocks\TotalsBlock;
use App\Dtos\Documents\DocumentBadge;
use App\Dtos\Documents\DocumentFact;
use App\Dtos\Documents\DocumentPage;
use App\Enums\Documents\DocumentTone;
use App\Enums\HR\PayslipItemType;
use App\Interfaces\Documents\DocumentBuilder;
use App\Models\HR\Payslip;
use App\Models\HR\PayslipItem;
use App\Utilities\Documents\DocumentFormat;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Monthly payslip: attendance summary, earnings and deductions, net pay. No QR (salary data stays off the public check).
 */
final class PayslipDocument implements DocumentBuilder
{
    private const DEDUCTIONS = [PayslipItemType::Deduction, PayslipItemType::Absence, PayslipItemType::Lateness,
        PayslipItemType::UnpaidLeave, PayslipItemType::SocialInsurance];

    public function __construct(private readonly DocumentFormat $format) {}

    public function with(): array
    {
        return ['employee.person', 'department', 'run', 'items'];
    }

    public function build(Model $record): DocumentPage
    {
        /** @var Payslip $slip */
        $slip = $record;
        $f = $this->format;
        $person = $slip->employee->person;
        $period = CarbonImmutable::create($slip->run->period_year, $slip->run->period_month, 1)?->format('F Y') ?? '';
        $row = fn (PayslipItem $i): array => [$i->name, $i->type->label(), $f->money($i->amount)];
        $items = $slip->items->sortBy('sort');
        $isDeduction = fn (PayslipItem $i): bool => in_array($i->type, self::DEDUCTIONS, true);

        return new DocumentPage(
            title: $f->text('payslip.title', ['period' => $period]),
            reference: $slip->run->code.'-'.$person->code,
            fileName: $f->fileName($f->text('payslip.file', ['period' => $period]), $person->code),
            badges: [new DocumentBadge($slip->run->status->label(), DocumentTone::Info)],
            highlights: [
                new DocumentFact($f->text('payslip.gross'), $f->money($slip->gross_salary), tone: DocumentTone::Info),
                new DocumentFact($f->text('payslip.deductions'), $f->money($slip->total_deductions), tone: DocumentTone::Danger),
                new DocumentFact($f->text('payslip.net'), $f->money($slip->net_salary), tone: DocumentTone::Success),
                $slip->run->paid_on ? new DocumentFact($f->text('payslip.paid_on'), $f->date($slip->run->paid_on), tone: DocumentTone::Violet) : null,
            ],
            blocks: [
                new FactsBlock($f->text('payslip.employee'), [
                    new DocumentFact($f->text('common.name'), $person->full_name),
                    new DocumentFact($f->text('payslip.staff_no'), $person->code),
                    new DocumentFact($f->text('payslip.job'), $slip->employee->job_title),
                    new DocumentFact($f->text('payslip.department'), $slip->department->name),
                    DocumentFact::optional($f->text('payslip.bank'), $slip->employee->bank_name),
                    DocumentFact::optional($f->text('payslip.iban'), $slip->employee->iban ? '•••• '.substr($slip->employee->iban, -4) : null),
                ]),
                new FactsBlock($f->text('payslip.attendance'), [
                    new DocumentFact($f->text('payslip.working_days'), (string) $slip->working_days),
                    new DocumentFact($f->text('payslip.present'), $slip->present_days),
                    new DocumentFact($f->text('payslip.absent'), $slip->absent_days),
                    new DocumentFact($f->text('payslip.paid_leave'), $slip->paid_leave_days),
                    new DocumentFact($f->text('payslip.unpaid_leave'), $slip->unpaid_leave_days),
                    new DocumentFact($f->text('payslip.overtime'), $f->text('payslip.hours', ['h' => intdiv($slip->overtime_minutes, 60), 'm' => $slip->overtime_minutes % 60])),
                ]),
                new TableBlock($f->text('payslip.earnings'), $f->columns('payslip.columns'), $items->reject($isDeduction)->map($row)->values()->all(), numeric: [2]),
                new TableBlock($f->text('payslip.deductions'), $f->columns('payslip.columns'), $items->filter($isDeduction)->map($row)->values()->all(), numeric: [2], empty: $f->text('payslip.no_deductions')),
                new TotalsBlock([
                    new DocumentFact($f->text('payslip.gross_salary'), $f->money($slip->gross_salary)),
                    new DocumentFact($f->text('payslip.deductions'), '− '.$f->money($slip->total_deductions)),
                    $slip->carried_forward->isZero() ? null : new DocumentFact($f->text('payslip.carried'), $f->money($slip->carried_forward)),
                ], new DocumentFact($f->text('payslip.net'), $f->money($slip->net_salary))),
            ],
            verifyNote: $f->text('payslip.verify'),
        );
    }
}
