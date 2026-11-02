<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Dtos\Accounting\AutomaticEntryData;
use App\Dtos\Accounting\JournalLineData;
use App\Dtos\HR\AppliedDeductions;
use App\Dtos\HR\PayrollPaymentData;
use App\Dtos\HR\PayslipLine;
use App\Enums\Accounting\AccountMappingKey;
use App\Enums\HR\EmployeeStatus;
use App\Enums\HR\PayrollStatus;
use App\Events\HR\PayrollStatusChanged;
use App\Exceptions\HR\PayrollPeriodExistsException;
use App\Exceptions\HR\PayrollPeriodNotEndedException;
use App\Exceptions\Shared\InvalidStateTransitionException;
use App\Exceptions\Shared\SelfApprovalException;
use App\Interfaces\Accounting\JournalEntryServiceInterface;
use App\Interfaces\HR\AttendanceServiceInterface;
use App\Interfaces\HR\PayrollServiceInterface;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\HR\Employee;
use App\Models\HR\PayrollRun;
use App\Models\HR\Payslip;
use App\Models\HR\PayslipItem;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\MoneyHelper;
use Brick\Money\Money;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Monthly payroll: HR calculates every payslip from salaries, components and attendance; someone else approves it
 * (salary accrual posted, attendance locked); finance marks it paid (bank settlement posted).
 */
final class PayrollService implements PayrollServiceInterface
{
    public const RELATIONS = ['calculator.person', 'approver.person'];

    public function __construct(
        private readonly ApplyListQuery $listQuery,
        private readonly ClinicClock $clock,
        private readonly PayslipCalculator $calculator,
        private readonly AttendanceServiceInterface $attendance,
        private readonly JournalEntryServiceInterface $journal,
    ) {}

    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->runs(), $query);
    }

    public function find(string $id): PayrollRun
    {
        return $this->runs()->with(['payslips' => fn ($q) => $q->with(['employee.person', 'department'])])->findOrFail($id);
    }

    public function createRun(int $year, int $month): PayrollRun
    {
        $run = DB::transaction(function () use ($year, $month): PayrollRun {
            $existing = PayrollRun::query()->where('period_year', $year)->where('period_month', $month)->lockForUpdate()->get();
            $active = $existing->first(fn (PayrollRun $r): bool => $r->status !== PayrollStatus::Cancelled);
            if ($active !== null) {
                throw new PayrollPeriodExistsException(__('errors.PAYROLL_PERIOD_EXISTS', ['month' => $active->periodStart()->format('F Y'), 'code' => $active->code]), ['code' => $active->code]);
            }
            $code = sprintf('PR-%04d-%02d', $year, $month).($existing->isEmpty() ? '' : '-'.($existing->count() + 1));

            return PayrollRun::query()->create(['code' => $code, 'period_year' => $year, 'period_month' => $month, 'status' => PayrollStatus::Draft]);
        });

        return $this->find($run->id);
    }

    public function calculate(PayrollRun $run): PayrollRun
    {
        $run = DB::transaction(function () use ($run): PayrollRun {
            $run = $this->locked($run, [PayrollStatus::Draft, PayrollStatus::Calculated], PayrollStatus::Calculated);
            $from = $run->periodStart();
            $to = $run->periodEnd();
            if ($this->clock->today()->toDateString() <= $to->toDateString()) {
                throw new PayrollPeriodNotEndedException(__('errors.PAYROLL_PERIOD_NOT_ENDED', ['month' => $from->format('F Y'), 'date' => $to->addDay()->toDateString()]));
            }

            PayslipItem::query()->whereIn('payslip_id', $run->payslips()->select('id'))->delete();
            $run->payslips()->delete();
            $carried = $this->carriedFrom($run);
            $employees = Employee::query()->with('person')
                ->whereDate('hire_date', '<=', $to->toDateString())
                ->where(fn (Builder $q) => $q->whereNull('termination_date')->orWhereDate('termination_date', '>=', $from->toDateString()))
                ->where(fn (Builder $q) => $q->where('status', '!=', EmployeeStatus::Suspended->value)->orWhereNotNull('termination_date'))
                ->get();

            $totals = ['gross' => MoneyHelper::zero(), 'deductions' => MoneyHelper::zero(), 'net' => MoneyHelper::zero()];
            $count = 0;
            foreach ($employees as $employee) {
                $previous = $carried[$employee->id] ?? null;
                $result = $this->calculator->calculate($employee, $from, $to, $previous['amount'] ?? MoneyHelper::zero(), $previous['run'] ?? null);
                if ($result === null) {
                    continue;
                }
                $slip = $run->payslips()->create($result['values']);
                foreach ($result['lines'] as $sort => $line) {
                    /** @var PayslipLine $line */
                    $slip->items()->create(['type' => $line->type, 'name' => $line->name, 'amount' => $line->amount, 'sort' => $sort]);
                }
                $totals['gross'] = $totals['gross']->plus($slip->gross_salary);
                $totals['deductions'] = $totals['deductions']->plus($slip->total_deductions);
                $totals['net'] = $totals['net']->plus($slip->net_salary);
                $count++;
            }

            $run->forceFill([
                'status' => PayrollStatus::Calculated,
                'total_gross' => $totals['gross'],
                'total_deductions' => $totals['deductions'],
                'total_net' => $totals['net'],
                'employee_count' => $count,
                'calculated_at' => $this->clock->now(),
                'calculated_by' => Auth::id(),
            ])->save();

            return $run;
        });
        PayrollStatusChanged::dispatch($run);

        return $this->find($run->id);
    }

    public function approve(PayrollRun $run): PayrollRun
    {
        $run = DB::transaction(function () use ($run): PayrollRun {
            $run = $this->locked($run, [PayrollStatus::Calculated], PayrollStatus::Approved);
            if ($run->calculated_by === Auth::id()) {
                throw new SelfApprovalException;
            }
            $entry = $this->journal->postAutomatic(new AutomaticEntryData(
                $run,
                $run->periodEnd(),
                __('accounting.payroll_accrual', ['code' => $run->code, 'month' => $run->periodStart()->format('F Y')]),
                $this->accrualLines($run),
            ));
            $this->attendance->lockMonth($run->period_year, $run->period_month, true);
            $run->forceFill(['status' => PayrollStatus::Approved, 'approved_by' => Auth::id(), 'approved_at' => $this->clock->now(), 'accrual_journal_entry_id' => $entry->id])->save();

            return $run;
        });
        PayrollStatusChanged::dispatch($run);

        return $this->find($run->id);
    }

    public function markPaid(PayrollRun $run, PayrollPaymentData $data): PayrollRun
    {
        $run = DB::transaction(function () use ($run, $data): PayrollRun {
            $run = $this->locked($run, [PayrollStatus::Approved], PayrollStatus::Paid);
            $entry = $run->total_net->isPositive() ? $this->journal->postAutomatic(new AutomaticEntryData(
                $run,
                $data->paidOn,
                __('accounting.payroll_payment', ['code' => $run->code, 'month' => $run->periodStart()->format('F Y')]),
                [JournalLineData::debit(AccountMappingKey::SalariesPayable, $run->total_net), JournalLineData::credit(AccountMappingKey::Bank, $run->total_net, $data->reference)],
            )) : null;
            $run->forceFill(['status' => PayrollStatus::Paid, 'paid_on' => $data->paidOn->toDateString(), 'payment_reference' => $data->reference, 'payment_journal_entry_id' => $entry?->id])->save();

            return $run;
        });
        PayrollStatusChanged::dispatch($run);

        return $this->find($run->id);
    }

    public function cancel(PayrollRun $run, string $reason): PayrollRun
    {
        $run = DB::transaction(function () use ($run, $reason): PayrollRun {
            $run = $this->locked($run, [PayrollStatus::Draft, PayrollStatus::Calculated, PayrollStatus::Approved], PayrollStatus::Cancelled);
            if ($run->status === PayrollStatus::Approved) {
                $entry = JournalEntry::query()->findOrFail($run->accrual_journal_entry_id);
                $this->journal->reverse($entry, $this->clock->today(), $reason, fromSource: true);
                $this->attendance->lockMonth($run->period_year, $run->period_month, false);
            }
            $run->forceFill(['status' => PayrollStatus::Cancelled, 'cancellation_reason' => $reason])->save();

            return $run;
        });
        PayrollStatusChanged::dispatch($run);

        return $this->find($run->id);
    }

    public function payslips(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->slips(), $query);
    }

    public function payslip(string $id): Payslip
    {
        return $this->slips()->with(['items', 'employee.user', 'employee.person', 'department'])->findOrFail($id);
    }

    /**
     * Dr salaries expense by department / Cr salaries payable (net), social insurance payable and payroll deductions
     * payable — from the amounts each payslip actually takes.
     *
     * @return list<JournalLineData>
     */
    private function accrualLines(PayrollRun $run): array
    {
        $expense = [];
        $net = MoneyHelper::zero();
        $insurance = MoneyHelper::zero();
        $other = MoneyHelper::zero();
        foreach ($run->payslips()->with('items')->get() as $slip) {
            $applied = AppliedDeductions::fromItems($slip->items);
            $expense[$slip->department_id] = ($expense[$slip->department_id] ?? MoneyHelper::zero())->plus($applied->expense());
            $net = $net->plus($applied->net);
            $insurance = $insurance->plus($applied->socialInsurance);
            $other = $other->plus($applied->other);
        }
        $deductionsAccount = Account::query()->where('code', Config::string('hr.payroll.deductions_account'))->value('id') ?? AccountMappingKey::SalariesPayable;

        return [
            ...array_map(fn (string $department, Money $amount): JournalLineData => JournalLineData::debit(AccountMappingKey::SalariesExpense, $amount, departmentId: $department), array_keys($expense), array_values($expense)),
            JournalLineData::credit(AccountMappingKey::SalariesPayable, $net),
            JournalLineData::credit(AccountMappingKey::SocialInsurancePayable, $insurance),
            JournalLineData::credit($deductionsAccount, $other),
        ];
    }

    /**
     * Shortfalls the previous month's payslips carried forward, by employee.
     *
     * @return array<string, array{amount: Money, run: string}>
     */
    private function carriedFrom(PayrollRun $run): array
    {
        $previous = PayrollRun::query()->whereIn('status', [PayrollStatus::Approved->value, PayrollStatus::Paid->value])
            ->where(fn (Builder $q) => $q->where('period_year', '<', $run->period_year)
                ->orWhere(fn (Builder $q) => $q->where('period_year', $run->period_year)->where('period_month', '<', $run->period_month)))
            ->orderByDesc('period_year')->orderByDesc('period_month')->first();
        if ($previous === null) {
            return [];
        }

        return $previous->payslips()->where('carried_forward', '>', 0)->get()
            ->mapWithKeys(fn (Payslip $p): array => [$p->employee_id => ['amount' => $p->carried_forward, 'run' => $previous->code]])->all();
    }

    /**
     * @param  list<PayrollStatus>  $from
     */
    private function locked(PayrollRun $run, array $from, PayrollStatus $to): PayrollRun
    {
        $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);
        if (! in_array($run->status, $from, true)) {
            throw new InvalidStateTransitionException(context: ['from' => $run->status->value, 'to' => $to->value]);
        }

        return $run;
    }

    /**
     * @return Builder<PayrollRun>
     */
    private function runs(): Builder
    {
        return Auth::user()?->can('payroll.read_all') === true ? PayrollRun::query()->with(self::RELATIONS) : PayrollRun::query()->whereRaw('1 = 0');
    }

    /**
     * @return Builder<Payslip>
     */
    private function slips(): Builder
    {
        return OwnershipScope::apply(Payslip::query()->with(['employee.person', 'department', 'run']), 'payroll', Auth::user());
    }
}
