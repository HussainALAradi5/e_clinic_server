<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Dtos\Accounting\ReportTable;
use App\Enums\Accounting\AccountMappingKey;
use App\Enums\Accounting\AccountType;
use App\Enums\Accounting\BalanceSide;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\JournalEntryType;
use App\Enums\Accounting\ReportType;
use App\Enums\Identity\AuditAction;
use App\Enums\Payments\InvoiceStatus;
use App\Enums\Shared\ExportFormat;
use App\Enums\Shared\PaymentMethod;
use App\Enums\Transactions\TransactionDirection;
use App\Enums\Transactions\TransactionType;
use App\Exceptions\Accounting\ReportRangeTooLargeException;
use App\Interfaces\Accounting\AccountServiceInterface;
use App\Interfaces\Accounting\FinancialReportServiceInterface;
use App\Interfaces\Shared\AuditRecorder;
use App\Models\Accounting\Account;
use App\Utilities\Documents\TablePdfWriter;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Transfer\Xlsx\XlsxWriter;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Read-only reports over posted journal lines (and, for revenue by service / aging / cash flow, over the source
 * documents). Every report is a ReportTable, rendered by one client table and one export path.
 */
final class FinancialReportService implements FinancialReportServiceInterface
{
    public function __construct(
        private readonly AccountServiceInterface $accounts,
        private readonly ClinicClock $clock,
        private readonly AuditRecorder $audit,
        private readonly TablePdfWriter $pdf,
    ) {}

    public function trialBalance(CarbonImmutable $asOf, bool $includeZero = false): ReportTable
    {
        $sums = $this->sumsByAccount(fn (Builder $q) => $q->whereDate('e.entry_date', '<=', $asOf->toDateString()));
        $rows = [];
        $debit = BigDecimal::zero();
        $credit = BigDecimal::zero();
        foreach ($this->leafAccounts() as $account) {
            $net = ($sums[$account->id] ?? BigDecimal::zero());
            if ($net->isZero() && ! $includeZero) {
                continue;
            }
            $rows[] = [
                '_kind' => 'line',
                '_link' => ['type' => 'account', 'id' => $account->id],
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type->label(),
                'debit' => $net->isPositive() ? $this->money($net) : '',
                'credit' => $net->isNegative() ? $this->money($net->negated()) : '',
            ];
            $net->isPositive() ? $debit = $debit->plus($net) : $credit = $credit->plus($net->negated());
        }

        return new ReportTable(
            ReportType::TrialBalance,
            __('accounting.reports.trial_balance'),
            __('accounting.reports.as_of', ['date' => $asOf->toDateString()]),
            [$this->col('code', 'Code'), $this->col('name', 'Account'), $this->col('type', 'Type'), $this->col('debit', 'Debit', 'money'), $this->col('credit', 'Credit', 'money')],
            $rows,
            ['debit' => $this->money($debit), 'credit' => $this->money($credit)],
        );
    }

    public function generalLedger(Account $account, CarbonImmutable $from, CarbonImmutable $to): ReportTable
    {
        $this->assertRange($from, $to);
        $sign = $account->normal_balance === BalanceSide::Debit ? 1 : -1;
        $opening = $this->signed(($this->sumsByAccount(fn (Builder $q) => $q->where('l.account_id', $account->id)->whereDate('e.entry_date', '<', $from->toDateString()))[$account->id] ?? BigDecimal::zero()), $sign);

        $lines = $this->postedLines()
            ->where('l.account_id', $account->id)
            ->whereDate('e.entry_date', '>=', $from->toDateString())->whereDate('e.entry_date', '<=', $to->toDateString())
            ->orderBy('e.entry_date')->orderBy('e.entry_number')->orderBy('l.line_number')
            ->select(['e.id as entry_id', 'e.entry_date', 'e.entry_number', 'e.description as entry_description', 'l.description', 'l.debit', 'l.credit'])
            ->get();

        $balance = $opening;
        $debit = BigDecimal::zero();
        $credit = BigDecimal::zero();
        $rows = [];
        foreach ($lines as $line) {
            $d = $this->dec($line->debit);
            $c = $this->dec($line->credit);
            $balance = $balance->plus($this->signed($d->minus($c), $sign));
            $debit = $debit->plus($d);
            $credit = $credit->plus($c);
            $rows[] = [
                '_kind' => 'line',
                '_link' => ['type' => 'journal', 'id' => (string) $line->entry_id],
                'date' => substr((string) $line->entry_date, 0, 10),
                'number' => (string) $line->entry_number,
                'description' => (string) ($line->description ?: $line->entry_description),
                'debit' => $d->isZero() ? '' : $this->money($d),
                'credit' => $c->isZero() ? '' : $this->money($c),
                'balance' => $this->money($balance),
            ];
        }

        return new ReportTable(
            ReportType::GeneralLedger,
            __('accounting.reports.general_ledger', ['account' => "{$account->code} {$account->name}"]),
            $this->range($from, $to),
            [$this->col('date', 'Date', 'date'), $this->col('number', 'Entry'), $this->col('description', 'Description'), $this->col('debit', 'Debit', 'money'), $this->col('credit', 'Credit', 'money'), $this->col('balance', 'Balance', 'money')],
            $rows,
            ['debit' => $this->money($debit), 'credit' => $this->money($credit), 'balance' => $this->money($balance)],
            [$this->figure('Opening balance', $opening), $this->figure('Closing balance', $balance)],
        );
    }

    public function incomeStatement(CarbonImmutable $from, CarbonImmutable $to, ?string $departmentId = null, ?CarbonImmutable $compareFrom = null, ?CarbonImmutable $compareTo = null): ReportTable
    {
        $this->assertRange($from, $to);
        $compare = $compareFrom !== null && $compareTo !== null;
        if ($compare) {
            $this->assertRange($compareFrom, $compareTo);
        }
        $period = fn (CarbonImmutable $f, CarbonImmutable $t): array => $this->sumsByAccount(fn (Builder $q) => $q
            ->whereDate('e.entry_date', '>=', $f->toDateString())->whereDate('e.entry_date', '<=', $t->toDateString())
            ->where('e.type', '!=', JournalEntryType::Closing->value)
            ->when($departmentId !== null, fn (Builder $q) => $q->where('l.department_id', $departmentId)));
        $current = $period($from, $to);
        $previous = $compare ? $period($compareFrom, $compareTo) : [];

        $rows = [];
        $net = ['amount' => BigDecimal::zero(), 'compare' => BigDecimal::zero()];
        foreach ([[AccountType::Revenue, -1], [AccountType::Expense, 1]] as [$type, $sign]) {
            $rows[] = ['_kind' => 'group', 'name' => $type === AccountType::Revenue ? __('accounting.reports.revenue') : __('accounting.reports.expenses')];
            $sum = ['amount' => BigDecimal::zero(), 'compare' => BigDecimal::zero()];
            foreach ($this->leafAccounts($type) as $account) {
                $amount = $this->signed($current[$account->id] ?? BigDecimal::zero(), $sign);
                $other = $this->signed($previous[$account->id] ?? BigDecimal::zero(), $sign);
                if ($amount->isZero() && $other->isZero()) {
                    continue;
                }
                $rows[] = ['_kind' => 'line', '_link' => ['type' => 'account', 'id' => $account->id], 'code' => $account->code, 'name' => $account->name, 'amount' => $this->money($amount), 'compare' => $compare ? $this->money($other) : ''];
                $sum['amount'] = $sum['amount']->plus($amount);
                $sum['compare'] = $sum['compare']->plus($other);
            }
            $label = $type === AccountType::Revenue ? __('accounting.reports.total_revenue') : __('accounting.reports.total_expenses');
            $rows[] = ['_kind' => 'total', 'name' => $label, 'amount' => $this->money($sum['amount']), 'compare' => $compare ? $this->money($sum['compare']) : ''];
            foreach (['amount', 'compare'] as $k) {
                $net[$k] = $type === AccountType::Revenue ? $net[$k]->plus($sum[$k]) : $net[$k]->minus($sum[$k]);
            }
        }
        $totals = ['name' => __('accounting.reports.net_profit'), 'amount' => $this->money($net['amount']), ...($compare ? ['compare' => $this->money($net['compare'])] : [])];

        $columns = [$this->col('code', 'Code'), $this->col('name', 'Account'), $this->col('amount', $this->range($from, $to), 'money')];
        if ($compare) {
            $columns[] = $this->col('compare', $this->range($compareFrom, $compareTo), 'money');
        }

        return new ReportTable(ReportType::IncomeStatement, __('accounting.reports.income_statement'), $this->range($from, $to), $columns, $rows, $totals);
    }

    public function balanceSheet(CarbonImmutable $asOf): ReportTable
    {
        $sums = $this->sumsByAccount(fn (Builder $q) => $q->whereDate('e.entry_date', '<=', $asOf->toDateString()));
        $rows = [];
        $section = function (AccountType $type, int $sign) use ($sums, &$rows): BigDecimal {
            $rows[] = ['_kind' => 'group', 'name' => $type->label()];
            $total = BigDecimal::zero();
            foreach ($this->leafAccounts($type) as $account) {
                $amount = $this->signed($sums[$account->id] ?? BigDecimal::zero(), $sign);
                if ($amount->isZero()) {
                    continue;
                }
                $rows[] = ['_kind' => 'line', '_link' => ['type' => 'account', 'id' => $account->id], 'code' => $account->code, 'name' => $account->name, 'amount' => $this->money($amount)];
                $total = $total->plus($amount);
            }

            return $total;
        };

        $assets = $section(AccountType::Asset, 1);
        $rows[] = ['_kind' => 'total', 'name' => __('accounting.reports.total_assets'), 'amount' => $this->money($assets)];
        $liabilities = $section(AccountType::Liability, -1);
        $equity = $section(AccountType::Equity, -1);
        // Revenue and expenses not moved to retained earnings yet (current-year result).
        $earnings = BigDecimal::zero();
        foreach ([...$this->leafAccounts(AccountType::Revenue), ...$this->leafAccounts(AccountType::Expense)] as $account) {
            $earnings = $earnings->minus($sums[$account->id] ?? BigDecimal::zero());
        }
        $rows[] = ['_kind' => 'line', 'code' => '', 'name' => __('accounting.reports.current_earnings'), 'amount' => $this->money($earnings)];
        $rows[] = ['_kind' => 'total', 'name' => __('accounting.reports.total_liabilities_equity'), 'amount' => $this->money($liabilities->plus($equity)->plus($earnings))];

        return new ReportTable(
            ReportType::BalanceSheet,
            __('accounting.reports.balance_sheet'),
            __('accounting.reports.as_of', ['date' => $asOf->toDateString()]),
            [$this->col('code', 'Code'), $this->col('name', 'Account'), $this->col('amount', 'Amount', 'money')],
            $rows,
            [],
            [$this->figure('Total assets', $assets), $this->figure('Liabilities + equity', $liabilities->plus($equity)->plus($earnings))],
        );
    }

    public function cashFlow(CarbonImmutable $from, CarbonImmutable $to): ReportTable
    {
        $this->assertRange($from, $to);
        $movements = DB::table('v_money_movements')
            ->whereDate('movement_date', '>=', $from->toDateString())->whereDate('movement_date', '<=', $to->toDateString())
            ->groupBy('movement_type', 'direction', 'method')
            ->selectRaw('movement_type, direction, method, sum(amount) as amount, count(*) as documents')
            ->orderBy('movement_type')->orderBy('method')
            ->get();

        $in = BigDecimal::zero();
        $out = BigDecimal::zero();
        $rows = [];
        foreach ($movements as $m) {
            $amount = $this->dec($m->amount);
            $direction = TransactionDirection::from((string) $m->direction);
            $rows[] = [
                '_kind' => 'line',
                'type' => TransactionType::from((string) $m->movement_type)->label(),
                'method' => $m->method !== null ? PaymentMethod::from((string) $m->method)->label() : '',
                'documents' => (string) $m->documents,
                'in' => $direction === TransactionDirection::In ? $this->money($amount) : '',
                'out' => $direction === TransactionDirection::Out ? $this->money($amount) : '',
            ];
            match ($direction) {
                TransactionDirection::In => $in = $in->plus($amount),
                TransactionDirection::Out => $out = $out->plus($amount),
                TransactionDirection::Internal => null,
            };
        }

        $cashAccounts = array_map(fn (AccountMappingKey $k): string => $this->accounts->resolve($k)->id, [AccountMappingKey::CashOnHand, AccountMappingKey::Bank, AccountMappingKey::CardClearing]);
        $balance = fn (string $op, CarbonImmutable $date): BigDecimal => array_reduce(
            array_values($this->sumsByAccount(fn (Builder $q) => $q->whereIn('l.account_id', $cashAccounts)->whereDate('e.entry_date', $op, $date->toDateString()))),
            fn (BigDecimal $sum, BigDecimal $v): BigDecimal => $sum->plus($v),
            BigDecimal::zero(),
        );

        return new ReportTable(
            ReportType::CashFlow,
            __('accounting.reports.cash_flow'),
            $this->range($from, $to),
            [$this->col('type', 'Movement'), $this->col('method', 'Method'), $this->col('documents', 'Documents', 'number'), $this->col('in', 'In', 'money'), $this->col('out', 'Out', 'money')],
            $rows,
            ['in' => $this->money($in), 'out' => $this->money($out)],
            [
                $this->figure('Opening cash and bank', $balance('<', $from)),
                $this->figure('Net cash flow', $in->minus($out)),
                $this->figure('Closing cash and bank', $balance('<=', $to)),
            ],
        );
    }

    public function revenueByDoctor(CarbonImmutable $from, CarbonImmutable $to): ReportTable
    {
        $this->assertRange($from, $to);
        $rows = $this->postedLines()
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('persons as p', 'p.id', '=', 'l.doctor_id')
            ->where('a.type', AccountType::Revenue->value)
            ->where('e.type', '!=', JournalEntryType::Closing->value)
            ->whereNotNull('l.doctor_id')
            ->whereDate('e.entry_date', '>=', $from->toDateString())->whereDate('e.entry_date', '<=', $to->toDateString())
            ->groupBy('l.doctor_id', 'p.first_name', 'p.last_name')
            ->selectRaw('l.doctor_id, p.first_name, p.last_name, sum(l.credit) - sum(l.debit) as revenue')
            ->get()
            ->map(fn (object $r): array => [
                '_kind' => 'line',
                '_link' => ['type' => 'doctor', 'id' => (string) $r->doctor_id],
                'doctor' => trim($r->first_name.' '.$r->last_name),
                'revenue' => $this->dec($r->revenue),
            ])
            ->sortByDesc(fn (array $r): float => $r['revenue']->toFloat())
            ->values();

        $total = $rows->reduce(fn (BigDecimal $s, array $r): BigDecimal => $s->plus($r['revenue']), BigDecimal::zero());

        return new ReportTable(
            ReportType::RevenueByDoctor,
            __('accounting.reports.revenue_by_doctor'),
            $this->range($from, $to),
            [$this->col('doctor', 'Doctor'), $this->col('revenue', 'Net revenue', 'money'), $this->col('share', 'Share', 'number')],
            array_values($rows->map(fn (array $r): array => [...$r, 'revenue' => $this->money($r['revenue']), 'share' => $this->share($r['revenue'], $total)])->all()),
            ['revenue' => $this->money($total), 'share' => $total->isZero() ? '' : '100.0'],
        );
    }

    public function revenueByService(CarbonImmutable $from, CarbonImmutable $to): ReportTable
    {
        $this->assertRange($from, $to);
        $issued = [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Paid->value];
        $rows = DB::table('invoice_items as i')
            ->join('invoices as v', 'v.id', '=', 'i.invoice_id')
            ->leftJoin('billable_services as s', 's.id', '=', 'i.billable_service_id')
            ->where('v.is_deleted', false)
            ->whereIn('v.status', $issued)
            ->whereDate('v.issue_date', '>=', $from->toDateString())->whereDate('v.issue_date', '<=', $to->toDateString())
            ->groupBy('i.billable_service_id', 's.code', 's.name')
            ->selectRaw('i.billable_service_id, s.code, s.name, sum(i.quantity) as quantity, sum(i.line_total) - sum(i.tax_amount) as net')
            ->get()
            ->map(fn (object $r): array => [
                '_kind' => 'line',
                'code' => (string) ($r->code ?? ''),
                'service' => (string) ($r->name ?? __('accounting.reports.other_items')),
                'quantity' => (string) $this->dec($r->quantity)->stripTrailingZeros(),
                'net' => $this->dec($r->net),
            ])
            ->sortByDesc(fn (array $r): float => $r['net']->toFloat())
            ->values();

        $total = $rows->reduce(fn (BigDecimal $s, array $r): BigDecimal => $s->plus($r['net']), BigDecimal::zero());

        return new ReportTable(
            ReportType::RevenueByService,
            __('accounting.reports.revenue_by_service'),
            $this->range($from, $to),
            [$this->col('code', 'Code'), $this->col('service', 'Service'), $this->col('quantity', 'Quantity', 'number'), $this->col('net', 'Net revenue', 'money'), $this->col('share', 'Share', 'number')],
            array_values($rows->map(fn (array $r): array => [...$r, 'net' => $this->money($r['net']), 'share' => $this->share($r['net'], $total)])->all()),
            ['net' => $this->money($total)],
        );
    }

    public function receivablesAging(CarbonImmutable $asOf): ReportTable
    {
        $buckets = ['current' => [0, 30], 'days_31_60' => [31, 60], 'days_61_90' => [61, 90], 'days_90_plus' => [91, PHP_INT_MAX]];
        $invoices = DB::table('invoices as v')
            ->join('persons as p', 'p.id', '=', 'v.patient_id')
            ->where('v.is_deleted', false)
            ->whereIn('v.status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])
            ->where('v.balance_due', '>', 0)
            ->whereDate('v.issue_date', '<=', $asOf->toDateString())
            ->select(['v.patient_id', 'p.code', 'p.first_name', 'p.last_name', 'v.due_date', 'v.issue_date', 'v.balance_due'])
            ->get();

        $zero = fn (): array => array_map(fn (): BigDecimal => BigDecimal::zero(), $buckets);
        $totals = $zero();
        $byPatient = [];
        foreach ($invoices as $invoice) {
            $days = (int) CarbonImmutable::parse((string) ($invoice->due_date ?? $invoice->issue_date))->diffInDays($asOf, false);
            $bucket = (string) collect($buckets)->search(fn (array $range): bool => max(0, $days) >= $range[0] && max(0, $days) <= $range[1]);
            $id = (string) $invoice->patient_id;
            $byPatient[$id] ??= ['patient' => trim($invoice->first_name.' '.$invoice->last_name), 'file' => (string) $invoice->code, 'amounts' => $zero()];
            $amount = $this->dec($invoice->balance_due);
            $byPatient[$id]['amounts'][$bucket] = $byPatient[$id]['amounts'][$bucket]->plus($amount);
            $totals[$bucket] = $totals[$bucket]->plus($amount);
        }

        $rows = collect($byPatient)->map(function (array $p, string $id): array {
            $total = array_reduce($p['amounts'], fn (BigDecimal $s, BigDecimal $v): BigDecimal => $s->plus($v), BigDecimal::zero());

            return [
                '_kind' => 'line',
                '_link' => ['type' => 'patient', 'id' => $id],
                'patient' => $p['patient'],
                'file' => $p['file'],
                ...array_map(fn (BigDecimal $v): string => $v->isZero() ? '' : $this->money($v), $p['amounts']),
                'total' => $this->money($total),
                '_sort' => $total->toFloat(),
            ];
        })->sortByDesc('_sort')->map(fn (array $r): array => array_diff_key($r, ['_sort' => true]))->values()->all();
        $grand = array_reduce($totals, fn (BigDecimal $s, BigDecimal $v): BigDecimal => $s->plus($v), BigDecimal::zero());

        return new ReportTable(
            ReportType::ReceivablesAging,
            __('accounting.reports.receivables_aging'),
            __('accounting.reports.as_of', ['date' => $asOf->toDateString()]),
            [
                $this->col('patient', 'Patient'), $this->col('file', 'File no.'),
                $this->col('current', '0–30 days', 'money'), $this->col('days_31_60', '31–60 days', 'money'),
                $this->col('days_61_90', '61–90 days', 'money'), $this->col('days_90_plus', 'Over 90 days', 'money'),
                $this->col('total', 'Total', 'money'),
            ],
            array_values($rows),
            [...array_map(fn (BigDecimal $v): string => $this->money($v), $totals), 'total' => $this->money($grand)],
        );
    }

    public function build(ReportType $type, array $params): ReportTable
    {
        $date = fn (string $key): CarbonImmutable => CarbonImmutable::parse((string) ($params[$key] ?? $this->clock->today()->toDateString()));
        $optional = fn (string $key): ?CarbonImmutable => empty($params[$key]) ? null : CarbonImmutable::parse((string) $params[$key]);

        return match ($type) {
            ReportType::TrialBalance => $this->trialBalance($date('as_of'), (bool) ($params['include_zero'] ?? false)),
            ReportType::GeneralLedger => $this->generalLedger(Account::query()->findOrFail((string) ($params['account_id'] ?? '')), $date('from'), $date('to')),
            ReportType::IncomeStatement => $this->incomeStatement($date('from'), $date('to'), isset($params['department_id']) ? (string) $params['department_id'] : null, $optional('compare_from'), $optional('compare_to')),
            ReportType::BalanceSheet => $this->balanceSheet($date('as_of')),
            ReportType::CashFlow => $this->cashFlow($date('from'), $date('to')),
            ReportType::RevenueByDoctor => $this->revenueByDoctor($date('from'), $date('to')),
            ReportType::RevenueByService => $this->revenueByService($date('from'), $date('to')),
            ReportType::ReceivablesAging => $this->receivablesAging($date('as_of')),
        };
    }

    public function export(ReportType $type, array $params, ExportFormat $format): array
    {
        $table = $this->build($type, $params);
        $path = (string) tempnam(sys_get_temp_dir(), 'rpt');
        if ($format === ExportFormat::Pdf) {
            $this->pdf->write($path, $table->title, $table->subtitle, $table->headers(), $table->cells());
        } else {
            $writer = new XlsxWriter;
            $sheet = $writer->addSheet(mb_substr($table->title, 0, 31))->banner($table->title, $table->subtitle, count($table->headers()));
            $sheet->header($table->headers());
            foreach ($table->cells() as $cells) {
                $sheet->row($cells);
            }
            $writer->save($path);
        }
        $this->audit->record(AuditAction::Exported, null, [], ['report' => $type->value, 'format' => $format->value, 'params' => $params], 'accounting');

        return [$path, str_replace('_', '-', $type->value).'-'.$this->clock->today()->toDateString().'.'.$format->value];
    }

    /**
     * Net debit − credit per account of posted lines (reversed originals included: their reversal cancels them).
     *
     * @param  callable(Builder): mixed  $scope
     * @return array<string, BigDecimal>
     */
    private function sumsByAccount(callable $scope): array
    {
        $query = $this->postedLines();
        $scope($query);

        return $query->groupBy('l.account_id')
            ->selectRaw('l.account_id, sum(l.debit) - sum(l.credit) as net')
            ->pluck('net', 'account_id')
            ->map(fn (mixed $net): BigDecimal => $this->dec($net))
            ->all();
    }

    private function postedLines(): Builder
    {
        return DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_deleted', false)
            ->whereIn('e.status', [JournalEntryStatus::Posted->value, JournalEntryStatus::Reversed->value]);
    }

    /**
     * @return Collection<int, Account>
     */
    private function leafAccounts(?AccountType $type = null): Collection
    {
        return Account::query()->withDeleted()->where('is_group', false)
            ->when($type !== null, fn ($q) => $q->where('type', $type))
            ->orderBy('code')->get()->toBase();
    }

    private function assertRange(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $months = Config::integer('accounting.report_max_months');
        if ($to->lt($from) || $from->addMonths($months)->lt($to)) {
            throw new ReportRangeTooLargeException(__('errors.REPORT_RANGE_TOO_LARGE', ['months' => $months]), ['max_months' => $months]);
        }
    }

    private function signed(BigDecimal $value, int $sign): BigDecimal
    {
        return $sign === 1 ? $value : $value->negated();
    }

    private function dec(mixed $value): BigDecimal
    {
        return BigDecimal::of(is_numeric($value) ? (string) $value : '0')->toScale(3, RoundingMode::HALF_UP);
    }

    private function money(BigDecimal $value): string
    {
        return (string) $value->toScale(3, RoundingMode::HALF_UP);
    }

    private function share(BigDecimal $part, BigDecimal $total): string
    {
        return $total->isZero() ? '' : (string) $part->multipliedBy(100)->dividedBy($total, 1, RoundingMode::HALF_UP);
    }

    private function range(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return $from->toDateString().' – '.$to->toDateString();
    }

    /**
     * @return array{key: string, label: string, type: string}
     */
    private function col(string $key, string $label, string $type = 'text'): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type];
    }

    /**
     * @return array{label: string, value: string, type: string}
     */
    private function figure(string $label, BigDecimal $value): array
    {
        return ['label' => $label, 'value' => $this->money($value), 'type' => 'money'];
    }
}
