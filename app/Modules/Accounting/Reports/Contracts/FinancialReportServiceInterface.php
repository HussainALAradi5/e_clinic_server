<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Reports\Contracts;

use App\Enums\Accounting\ReportType;
use App\Modules\Accounting\Accounts\Models\Account;
use App\Modules\Accounting\Reports\Data\ReportTable;
use App\Shared\Enums\ExportFormat;
use Carbon\CarbonImmutable;

interface FinancialReportServiceInterface
{
    /** Debit / credit balance of every leaf account as of a date; the two totals match. */
    public function trialBalance(CarbonImmutable $asOf, bool $includeZero = false): ReportTable;

    /** Opening balance, every posted line with a running balance, closing balance. */
    public function generalLedger(Account $account, CarbonImmutable $from, CarbonImmutable $to): ReportTable;

    /** Revenue − expenses = net profit per account, optionally per cost centre and against a comparison period. */
    public function incomeStatement(CarbonImmutable $from, CarbonImmutable $to, ?string $departmentId = null, ?CarbonImmutable $compareFrom = null, ?CarbonImmutable $compareTo = null): ReportTable;

    /** Assets = liabilities + equity (+ earnings not closed yet). */
    public function balanceSheet(CarbonImmutable $asOf): ReportTable;

    /** Money in / out per movement type and method (v_money_movements), with opening and closing cash. */
    public function cashFlow(CarbonImmutable $from, CarbonImmutable $to): ReportTable;

    /** Net revenue per doctor (journal lines carrying the doctor). */
    public function revenueByDoctor(CarbonImmutable $from, CarbonImmutable $to): ReportTable;

    /** Invoiced quantity and net revenue per billable service (issued invoices). */
    public function revenueByService(CarbonImmutable $from, CarbonImmutable $to): ReportTable;

    /** Open patient balances by age of the invoice: 0–30, 31–60, 61–90, 90+ days. */
    public function receivablesAging(CarbonImmutable $asOf): ReportTable;

    /**
     * Any report from its type and query parameters (validated by the caller).
     *
     * @param  array<string, mixed>  $params
     */
    public function build(ReportType $type, array $params): ReportTable;

    /**
     * Writes the report to a temporary Excel or PDF file; returns [absolute path, download name].
     *
     * @param  array<string, mixed>  $params
     * @return array{0: string, 1: string}
     */
    public function export(ReportType $type, array $params, ExportFormat $format): array;
}
