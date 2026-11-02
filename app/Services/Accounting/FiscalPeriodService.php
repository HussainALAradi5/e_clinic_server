<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Dtos\Accounting\AutomaticEntryData;
use App\Dtos\Accounting\FiscalYearData;
use App\Dtos\Accounting\JournalLineData;
use App\Enums\Accounting\AccountMappingKey;
use App\Enums\Accounting\AccountType;
use App\Enums\Accounting\FiscalStatus;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\JournalEntryType;
use App\Enums\Identity\AuditAction;
use App\Exceptions\Accounting\ClosedFiscalPeriodException;
use App\Exceptions\Accounting\FiscalPeriodHasDraftsException;
use App\Exceptions\Accounting\FiscalPeriodLockedException;
use App\Exceptions\Accounting\FiscalYearOverlapException;
use App\Exceptions\Accounting\PreviousPeriodOpenException;
use App\Exceptions\Shared\InvalidStateTransitionException;
use App\Interfaces\Accounting\FiscalPeriodServiceInterface;
use App\Interfaces\Accounting\JournalEntryServiceInterface;
use App\Interfaces\Shared\AuditRecorder;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\FiscalYear;
use App\Utilities\Shared\ClinicClock;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Fiscal years and monthly periods. Balances are cumulative over the journal (no opening entries are needed):
 * the year-end closing entry moves the year's revenue and expenses into retained earnings.
 */
final class FiscalPeriodService implements FiscalPeriodServiceInterface
{
    public function __construct(
        private readonly ClinicClock $clock,
        private readonly AuditRecorder $audit,
    ) {}

    public function listYears(): Collection
    {
        return FiscalYear::query()->with('periods')->orderByDesc('start_date')->get()->toBase();
    }

    public function findYear(string $id): FiscalYear
    {
        return FiscalYear::query()->with('periods')->findOrFail($id);
    }

    public function findPeriod(string $id): FiscalPeriod
    {
        return FiscalPeriod::query()->with('fiscalYear')->findOrFail($id);
    }

    public function createYear(FiscalYearData $data): FiscalYear
    {
        return DB::transaction(function () use ($data): FiscalYear {
            $overlaps = FiscalYear::query()
                ->whereDate('start_date', '<=', $data->endDate->toDateString())
                ->whereDate('end_date', '>=', $data->startDate->toDateString())
                ->exists();
            if ($overlaps) {
                throw new FiscalYearOverlapException;
            }

            $year = FiscalYear::query()->create([
                'name' => $data->name,
                'start_date' => $data->startDate,
                'end_date' => $data->endDate,
                'status' => FiscalStatus::Open,
            ]);

            $number = 0;
            for ($start = $data->startDate; $start->lte($data->endDate); $start = $start->addMonthNoOverflow()->startOfMonth()) {
                $end = $start->endOfMonth()->startOfDay()->min($data->endDate);
                $year->periods()->create([
                    'period_number' => ++$number,
                    'name' => $start->format('Y-m'),
                    'start_date' => $start,
                    'end_date' => $end,
                    'status' => FiscalStatus::Open,
                ]);
            }
            if ($data->withAdjustmentPeriod) {
                $year->periods()->create([
                    'period_number' => 13,
                    'name' => $data->endDate->format('Y').'-ADJ',
                    'start_date' => $data->endDate,
                    'end_date' => $data->endDate,
                    'status' => FiscalStatus::Open,
                ]);
            }

            return $this->findYear($year->id);
        });
    }

    public function periodFor(CarbonInterface $date): FiscalPeriod
    {
        $period = $this->periodContaining($date);
        if (! $period->isOpen()) {
            throw new ClosedFiscalPeriodException(context: ['date' => $date->toDateString(), 'period' => $period->name, 'status' => $period->status->value]);
        }

        return $period;
    }

    public function periodContaining(CarbonInterface $date): FiscalPeriod
    {
        $day = $date->toDateString();
        // A regular month wins over the one-day adjustment period 13; an open one over a closed one.
        $period = FiscalPeriod::query()
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day)
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderBy('period_number')
            ->first();

        return $period ?? throw new ClosedFiscalPeriodException(__('errors.FISCAL_PERIOD_MISSING', ['date' => $day]), ['date' => $day]);
    }

    public function closePeriod(FiscalPeriod $period): FiscalPeriod
    {
        return DB::transaction(function () use ($period): FiscalPeriod {
            $period = FiscalPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if (! $period->status->canTransitionTo(FiscalStatus::Closed)) {
                throw new InvalidStateTransitionException(context: ['from' => $period->status->value, 'to' => FiscalStatus::Closed->value]);
            }

            $earlierOpen = FiscalPeriod::query()
                ->where('status', FiscalStatus::Open->value)
                ->whereDate('end_date', '<', $period->start_date->toDateString())
                ->orderBy('start_date')
                ->value('name');
            if ($earlierOpen !== null) {
                throw new PreviousPeriodOpenException(context: ['period' => $earlierOpen]);
            }

            $blockers = $this->closeBlockers($period->start_date, $period->end_date);
            if ($blockers !== []) {
                throw new FiscalPeriodHasDraftsException(context: ['blockers' => $blockers]);
            }

            $period->forceFill(['status' => FiscalStatus::Closed, 'closed_by' => Auth::id(), 'closed_at' => $this->clock->now()])->save();

            return $this->findPeriod($period->id);
        });
    }

    public function reopenPeriod(FiscalPeriod $period, string $reason): FiscalPeriod
    {
        return DB::transaction(function () use ($period, $reason): FiscalPeriod {
            $period = FiscalPeriod::query()->with('fiscalYear')->lockForUpdate()->findOrFail($period->id);
            if ($period->status === FiscalStatus::Locked) {
                throw new FiscalPeriodLockedException;
            }
            if ($period->status !== FiscalStatus::Closed || $period->fiscalYear->status !== FiscalStatus::Open) {
                throw new InvalidStateTransitionException(context: ['from' => $period->status->value, 'to' => FiscalStatus::Open->value]);
            }

            $laterClosed = FiscalPeriod::query()
                ->where('status', '!=', FiscalStatus::Open->value)
                ->whereDate('start_date', '>', $period->end_date->toDateString())
                ->orderByDesc('start_date')
                ->value('name');
            if ($laterClosed !== null) {
                throw new InvalidStateTransitionException(__('errors.LATER_PERIOD_CLOSED', ['period' => $laterClosed]), ['period' => $laterClosed]);
            }

            $period->forceFill(['status' => FiscalStatus::Open, 'closed_by' => null, 'closed_at' => null])->saveQuietly();
            $this->audit->record(AuditAction::StatusChanged, $period, ['status' => FiscalStatus::Closed->value], ['status' => FiscalStatus::Open->value, 'reason' => $reason]);

            return $this->findPeriod($period->id);
        });
    }

    public function closeYear(FiscalYear $year): FiscalYear
    {
        return DB::transaction(function () use ($year): FiscalYear {
            $year = FiscalYear::query()->with('periods')->lockForUpdate()->findOrFail($year->id);
            if (! $year->status->canTransitionTo(FiscalStatus::Closed)) {
                throw new InvalidStateTransitionException(context: ['from' => $year->status->value, 'to' => FiscalStatus::Closed->value]);
            }

            $open = $year->periods->first(fn (FiscalPeriod $p): bool => $p->isOpen());
            if ($open instanceof FiscalPeriod) {
                throw new PreviousPeriodOpenException(context: ['period' => $open->name]);
            }
            $blockers = $this->closeBlockers($year->start_date, $year->end_date);
            if ($blockers !== []) {
                throw new FiscalPeriodHasDraftsException(context: ['blockers' => $blockers]);
            }

            $closing = $this->postClosingEntry($year);
            $year->forceFill([
                'status' => FiscalStatus::Closed,
                'closing_journal_entry_id' => $closing,
                'closed_by' => Auth::id(),
                'closed_at' => $this->clock->now(),
            ])->save();

            // The next year is ready for postings.
            $next = $year->end_date->addDay();
            if (! FiscalPeriod::query()->whereDate('start_date', '<=', $next->toDateString())->whereDate('end_date', '>=', $next->toDateString())->exists()) {
                $this->createYear(new FiscalYearData('FY'.$next->year, $next, $next->addYear()->subDay()));
            }

            return $this->findYear($year->id);
        });
    }

    public function lockYear(FiscalYear $year): FiscalYear
    {
        return DB::transaction(function () use ($year): FiscalYear {
            $year = FiscalYear::query()->lockForUpdate()->findOrFail($year->id);
            if (! $year->status->canTransitionTo(FiscalStatus::Locked) || $year->status !== FiscalStatus::Closed) {
                throw new InvalidStateTransitionException(context: ['from' => $year->status->value, 'to' => FiscalStatus::Locked->value]);
            }

            $year->forceFill(['status' => FiscalStatus::Locked])->save();
            $year->periods()->get()->each(fn (FiscalPeriod $p) => $p->forceFill(['status' => FiscalStatus::Locked])->save());

            return $this->findYear($year->id);
        });
    }

    /**
     * What still blocks closing the dates: draft journal entries and cash sessions not closed yet.
     *
     * @return array<string, int>
     */
    private function closeBlockers(CarbonImmutable $from, CarbonImmutable $to): array
    {
        [$fromUtc, $toUtc] = $this->clock->dayRangeUtc($from, $to);

        return array_filter([
            'draft_journal_entries' => DB::table('journal_entries')
                ->where('is_deleted', false)
                ->where('status', JournalEntryStatus::Draft->value)
                ->whereDate('entry_date', '>=', $from->toDateString())->whereDate('entry_date', '<=', $to->toDateString())
                ->count(),
            'open_cash_sessions' => DB::table('cash_sessions')
                ->where('is_deleted', false)
                ->whereIn('status', ['open', 'closing'])
                ->whereBetween('opened_at', [$fromUtc, $toUtc])
                ->count(),
        ]);
    }

    /** Zeroes every revenue / expense account of the year against retained earnings; returns the entry id (null when nothing to close). */
    private function postClosingEntry(FiscalYear $year): ?string
    {
        $balances = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.is_deleted', false)
            ->whereIn('e.status', [JournalEntryStatus::Posted->value, JournalEntryStatus::Reversed->value])
            ->where('e.type', '!=', JournalEntryType::Closing->value)
            ->whereDate('e.entry_date', '>=', $year->start_date->toDateString())->whereDate('e.entry_date', '<=', $year->end_date->toDateString())
            ->whereIn('a.type', [AccountType::Revenue->value, AccountType::Expense->value])
            ->groupBy('l.account_id')
            ->selectRaw('l.account_id, sum(l.debit) as debit, sum(l.credit) as credit')
            ->get();

        $lines = [];
        $net = BigDecimal::zero();
        foreach ($balances as $row) {
            $balance = BigDecimal::of((string) $row->debit)->minus((string) $row->credit);
            if ($balance->isZero()) {
                continue;
            }
            // Debit balance (expense) is credited away and vice versa.
            $lines[] = $balance->isPositive()
                ? JournalLineData::credit((string) $row->account_id, $balance)
                : JournalLineData::debit((string) $row->account_id, $balance->negated());
            $net = $net->plus($balance);
        }
        if ($lines === []) {
            return null;
        }
        // $net > 0 = loss (debit retained earnings); < 0 = profit (credit retained earnings).
        $lines[] = $net->isPositive()
            ? JournalLineData::debit(AccountMappingKey::RetainedEarnings, $net)
            : JournalLineData::credit(AccountMappingKey::RetainedEarnings, $net->negated());

        return app(JournalEntryServiceInterface::class)->postAutomatic(new AutomaticEntryData(
            $year,
            $year->end_date,
            __('accounting.closing_entry', ['year' => $year->name]),
            $lines,
            JournalEntryType::Closing,
        ))->id;
    }
}
