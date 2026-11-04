<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Accounting\AccountMappingKey;
use App\Enums\Accounting\AccountType;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Appointments\AppointmentStatus;
use App\Enums\Payments\AppointmentFeeStatus;
use App\Enums\Payments\PaymentStatus;
use App\Interfaces\Accounting\AccountServiceInterface;
use App\Interfaces\Accounting\MonthSummaryServiceInterface;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Appointments\Appointment;
use App\Models\Appointments\Patient;
use App\Models\Payments\AppointmentFee;
use App\Models\Payments\PackageSession;
use App\Models\Payments\PatientPackage;
use App\Models\Payments\Payment;
use App\Models\Payments\Refund;
use App\Utilities\Shared\MoneyHelper;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class MonthSummaryService implements MonthSummaryServiceInterface
{
    public function __construct(private readonly AccountServiceInterface $accounts) {}

    public function summary(int $year, int $month): array
    {
        $from = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $previous = $from->subMonthNoOverflow();

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $from->endOfMonth()->toDateString()],
            'current' => $this->figures($from, $from->endOfMonth()),
            'previous' => $this->figures($previous, $previous->endOfMonth()),
            'top_doctors' => $this->topDoctors($from, $from->endOfMonth()),
        ];
    }

    /** @return array<string, mixed> */
    private function figures(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $revenue = $this->flow(AccountType::Revenue, $from, $to, credit: true);
        $expenses = $this->flow(AccountType::Expense, $from, $to, credit: false);
        $range = [$from->toDateString(), $to->toDateString()];
        $collected = Payment::query()->where('status', PaymentStatus::Completed->value)
            ->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('method, SUM(amount) AS total')->groupBy('method')->pluck('total', 'method');
        $visits = Appointment::query()->whereBetween('scheduled_date', $range)
            ->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');

        return [
            'revenue' => MoneyHelper::toApi($revenue),
            'expenses' => MoneyHelper::toApi($expenses),
            'net_result' => MoneyHelper::toApi($revenue->minus($expenses)),
            'collected' => MoneyHelper::toApi($this->sum($collected->all())),
            'collected_by_method' => collect($collected)->map(fn ($v, $m): array => ['method' => $m, 'total' => MoneyHelper::toApi(MoneyHelper::of((string) $v))])->values()->all(),
            'refunded' => MoneyHelper::toApi(MoneyHelper::of((string) (Refund::query()->whereNotNull('processed_at')->whereBetween('processed_at', [$from->startOfDay(), $to->endOfDay()])->sum('amount') ?: '0'))),
            'owed_by_patients' => MoneyHelper::toApi($this->balance(AccountMappingKey::AccountsReceivable, $to)),
            'owed_by_insurers' => MoneyHelper::toApi($this->balance(AccountMappingKey::InsuranceReceivable, $to)),
            'patient_balances_held' => MoneyHelper::toApi($this->balance(AccountMappingKey::PatientDeposits, $to)->negated()),
            'unearned_packages' => MoneyHelper::toApi($this->balance(AccountMappingKey::UnearnedPackages, $to)->negated()),
            'visits_completed' => (int) ($visits[AppointmentStatus::Completed->value] ?? 0),
            'no_shows' => (int) ($visits[AppointmentStatus::NoShow->value] ?? 0),
            'cancellations' => (int) ($visits[AppointmentStatus::Cancelled->value] ?? 0),
            'new_patients' => Patient::query()->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->count(),
            'fees_charged' => MoneyHelper::toApi(MoneyHelper::of((string) (AppointmentFee::query()->where('status', AppointmentFeeStatus::Charged->value)->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->sum('amount') ?: '0'))),
            'packages_sold' => PatientPackage::query()->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->count(),
            'package_sessions_used' => PackageSession::query()->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->count(),
        ];
    }

    /** Net movement on accounts of a type in the period (posted entries): credits − debits for revenue, the reverse for expenses. */
    private function flow(AccountType $type, CarbonImmutable $from, CarbonImmutable $to, bool $credit): Money
    {
        $net = $this->lines($from, $to)
            ->whereHas('account', fn (Builder $q) => $q->where('type', $type->value))
            ->selectRaw($credit ? 'COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) AS net' : 'COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS net')
            ->value('net');

        return MoneyHelper::of((string) ($net ?? '0'));
    }

    /** Debit balance of a mapped account at the end of the day (posted entries). */
    private function balance(AccountMappingKey $key, CarbonImmutable $asOf): Money
    {
        $net = JournalEntryLine::query()
            ->where('account_id', $this->accounts->resolve($key)->id)
            ->whereHas('entry', fn (Builder $q) => $q->where('status', JournalEntryStatus::Posted->value)->whereDate('entry_date', '<=', $asOf->toDateString()))
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS net')
            ->value('net');

        return MoneyHelper::of((string) ($net ?? '0'));
    }

    /** @return list<array{doctor_id: string, full_name: string, revenue: array{amount: string, currency: string}}> */
    private function topDoctors(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->lines($from, $to)
            ->whereNotNull('doctor_id')
            ->whereHas('account', fn (Builder $q) => $q->where('type', AccountType::Revenue->value))
            ->selectRaw('doctor_id, COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) AS net')
            ->groupBy('doctor_id')->orderByDesc('net')->limit(5)
            ->with('doctor.person')
            ->get()
            ->map(fn (JournalEntryLine $l): array => [
                'doctor_id' => (string) $l->doctor_id,
                'full_name' => (string) $l->doctor?->person->full_name,
                'revenue' => MoneyHelper::toApi(MoneyHelper::of((string) $l->getAttribute('net'))),
            ])->values()->all();
    }

    /** @return Builder<JournalEntryLine> */
    private function lines(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return JournalEntryLine::query()->whereHas('entry', fn (Builder $q) => $q->where('status', JournalEntryStatus::Posted->value)
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()]));
    }

    /** @param  array<string, mixed>  $totals */
    private function sum(array $totals): Money
    {
        return array_reduce($totals, fn (Money $s, $v): Money => $s->plus(MoneyHelper::of((string) $v)), MoneyHelper::zero());
    }
}
