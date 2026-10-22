<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Journal\Services;

use App\Enums\Accounting\AccountMappingKey;
use App\Enums\Accounting\FiscalStatus;
use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\JournalEntryType;
use App\Modules\Accounting\Accounts\Contracts\AccountServiceInterface;
use App\Modules\Accounting\Accounts\Exceptions\GroupAccountNotPostableException;
use App\Modules\Accounting\Accounts\Models\Account;
use App\Modules\Accounting\Fiscal\Contracts\FiscalPeriodServiceInterface;
use App\Modules\Accounting\Fiscal\Exceptions\FiscalPeriodLockedException;
use App\Modules\Accounting\Fiscal\Models\FiscalPeriod;
use App\Modules\Accounting\Journal\Contracts\JournalEntryServiceInterface;
use App\Modules\Accounting\Journal\Data\AutomaticEntryData;
use App\Modules\Accounting\Journal\Data\JournalEntryData;
use App\Modules\Accounting\Journal\Data\JournalLineData;
use App\Modules\Accounting\Journal\Events\JournalEntryPosted;
use App\Modules\Accounting\Journal\Exceptions\AutomaticEntryReversalException;
use App\Modules\Accounting\Journal\Exceptions\JournalEntryAlreadyReversedException;
use App\Modules\Accounting\Journal\Exceptions\JournalEntryNotEditableException;
use App\Modules\Accounting\Journal\Exceptions\UnbalancedJournalEntryException;
use App\Modules\Accounting\Journal\Models\JournalEntry;
use App\Modules\Accounting\Journal\Models\JournalEntryLine;
use App\Shared\Access\OwnershipScope;
use App\Shared\Exceptions\SelfApprovalException;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Query\ListQuery;
use App\Shared\Services\BaseEntityService;
use App\Shared\Support\ClinicClock;
use App\Shared\Support\DocumentNumberGenerator;
use App\Shared\Support\GenericEntityScopes;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * The general journal: manual drafts posted by accountants and automatic entries posted by source documents.
 * Posted entries never change; corrections are reversal entries.
 *
 * @extends BaseEntityService<JournalEntry>
 */
final class JournalEntryService extends BaseEntityService implements JournalEntryServiceInterface
{
    public const RELATIONS = [
        'lines.account', 'lines.department', 'lines.patient.person', 'lines.doctor.person',
        'fiscalPeriod', 'reversalOf', 'reversedBy', 'poster.person', 'creator.person',
    ];

    public function __construct(
        ApplyListQuery $listQuery,
        private readonly FiscalPeriodServiceInterface $fiscal,
        private readonly AccountServiceInterface $accounts,
        private readonly DocumentNumberGenerator $numbers,
        private readonly ClinicClock $clock,
    ) {
        parent::__construct($listQuery);
    }

    public function list(ListQuery $query, ?string $accountId = null): LengthAwarePaginator|CursorPaginator
    {
        $builder = $this->query()->when($accountId !== null, fn (Builder $q) => $q->whereHas('lines', fn (Builder $l) => $l->where('account_id', $accountId)));

        return $this->listQuery->paginate($builder, $query);
    }

    public function find(string $id): JournalEntry
    {
        return $this->query()->with(self::RELATIONS)->findOrFail($id);
    }

    public function findWithDeleted(string $id): JournalEntry
    {
        return GenericEntityScopes::withDeleted($this->query())->with(self::RELATIONS)->findOrFail($id);
    }

    public function createDraft(JournalEntryData $data): JournalEntry
    {
        return DB::transaction(function () use ($data): JournalEntry {
            $entry = new JournalEntry([
                'entry_date' => $data->entryDate,
                'fiscal_period_id' => $this->fiscal->periodContaining($data->entryDate)->id,
                'type' => $data->type,
                'status' => JournalEntryStatus::Draft,
                'description' => $data->description,
            ]);
            $entry->save();
            $this->writeLines($entry, $this->resolveLines($data->lines));

            return $this->find($entry->id);
        });
    }

    public function updateDraft(JournalEntry $entry, JournalEntryData $data): JournalEntry
    {
        return DB::transaction(function () use ($entry, $data): JournalEntry {
            $entry = $this->lockEditable($entry);
            $entry->fill([
                'entry_date' => $data->entryDate,
                'fiscal_period_id' => $this->fiscal->periodContaining($data->entryDate)->id,
                'type' => $data->type,
                'description' => $data->description,
            ]);
            $this->writeLines($entry, $this->resolveLines($data->lines));

            return $this->find($entry->id);
        });
    }

    public function deleteDraft(JournalEntry $entry): void
    {
        DB::transaction(fn () => $this->lockEditable($entry)->softDelete());
    }

    public function restoreDraft(JournalEntry $entry): JournalEntry
    {
        return $entry->isDeleted() ? $this->findWithDeleted(parent::restore($entry)->id) : $entry;
    }

    /** Bulk delete of drafts goes through the same guard. */
    public function delete(Model $model): void
    {
        $this->deleteDraft($model);
    }

    public function post(JournalEntry $entry): JournalEntry
    {
        $entry = DB::transaction(function () use ($entry): JournalEntry {
            $entry = $this->lockEditable($entry);
            $limit = BigDecimal::of(Config::string('accounting.self_post_limit'));
            if ($entry->created_by === Auth::id() && $entry->amount()->isGreaterThan($limit)) {
                throw new SelfApprovalException(__('errors.JOURNAL_SELF_POST', ['limit' => (string) $limit]), ['limit' => (string) $limit]);
            }

            return $this->finalize($entry, $entry->entry_date, postedBy: $this->actorId());
        });
        JournalEntryPosted::dispatch($entry);

        return $this->find($entry->id);
    }

    public function postAutomatic(AutomaticEntryData $data): JournalEntry
    {
        $entry = DB::transaction(function () use ($data): JournalEntry {
            $lines = array_values(array_filter($this->resolveLines($data->lines), fn (JournalLineData $l): bool => ! $l->isEmpty()));
            $period = $this->postingPeriod($data->date, $data->type);

            $entry = new JournalEntry([
                'entry_date' => $data->date->toDateString(),
                'fiscal_period_id' => $period->id,
                'type' => $data->type,
                'status' => JournalEntryStatus::Draft,
                'description' => mb_substr($data->description, 0, 500),
                'source_type' => $data->source->getMorphClass(),
                'source_id' => $data->source->getKey(),
            ]);
            $entry->save();
            $this->writeLines($entry, $lines);

            return $this->finalize($entry, $data->date, postedBy: null, period: $period);
        });
        JournalEntryPosted::dispatch($entry);

        return $entry;
    }

    public function reverse(JournalEntry $entry, CarbonInterface $date, string $reason, bool $fromSource = false): JournalEntry
    {
        $reversal = DB::transaction(function () use ($entry, $date, $reason, $fromSource): JournalEntry {
            $entry = JournalEntry::query()->with('lines')->lockForUpdate()->findOrFail($entry->id);
            if ($entry->status === JournalEntryStatus::Reversed) {
                throw new JournalEntryAlreadyReversedException;
            }
            if ($entry->status !== JournalEntryStatus::Posted) {
                throw new JournalEntryNotEditableException;
            }
            if (! $entry->isManual() && ! $fromSource) {
                throw new AutomaticEntryReversalException;
            }

            $reversal = new JournalEntry([
                'entry_date' => $date->toDateString(),
                'fiscal_period_id' => $this->fiscal->periodFor($date)->id,
                'type' => JournalEntryType::Reversal,
                'status' => JournalEntryStatus::Draft,
                'description' => mb_substr(__('accounting.reversal_of', ['number' => $entry->entry_number, 'reason' => $reason]), 0, 500),
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'reversal_of_id' => $entry->id,
            ]);
            $reversal->save();
            $this->writeLines($reversal, array_values($entry->lines->map(fn (JournalEntryLine $l): JournalLineData => (new JournalLineData(
                $l->account_id, $l->debit, $l->credit, $l->description, $l->department_id, $l->patient_id, $l->doctor_id,
            ))->mirrored($l->account_id))->all()));
            $reversal = $this->finalize($reversal, $date, postedBy: $this->actorId());

            $entry->forceFill(['status' => JournalEntryStatus::Reversed])->save();

            return $reversal;
        });
        JournalEntryPosted::dispatch($reversal);

        return $this->find($reversal->id);
    }

    public function entryOf(Model $source): ?JournalEntry
    {
        return JournalEntry::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('status', JournalEntryStatus::Posted->value)
            ->where('type', '!=', JournalEntryType::Reversal->value)
            ->latest('posted_at')
            ->first();
    }

    /**
     * @return Builder<JournalEntry>
     */
    protected function query(): Builder
    {
        return OwnershipScope::apply(JournalEntry::query(), 'journal', Auth::user());
    }

    private function lockEditable(JournalEntry $entry): JournalEntry
    {
        $entry = JournalEntry::query()->lockForUpdate()->findOrFail($entry->id);
        if (! $entry->isDraft() || ! $entry->isManual()) {
            throw new JournalEntryNotEditableException;
        }

        return $entry;
    }

    /** Balanced, ≥ 2 lines, open period → number + posted. */
    /** Users are keyed by uuid; Auth::id() is typed int|string by the framework. */
    private function actorId(): ?string
    {
        $id = Auth::id();

        return $id === null ? null : (string) $id;
    }

    private function finalize(JournalEntry $entry, CarbonInterface $date, ?string $postedBy, ?FiscalPeriod $period = null): JournalEntry
    {
        $lines = $entry->lines()->get();
        $debit = $lines->reduce(fn (BigDecimal $sum, JournalEntryLine $l): BigDecimal => $sum->plus($l->debit), BigDecimal::zero());
        $credit = $lines->reduce(fn (BigDecimal $sum, JournalEntryLine $l): BigDecimal => $sum->plus($l->credit), BigDecimal::zero());
        if ($lines->count() < 2 || ! $debit->isEqualTo($credit) || $debit->isZero()) {
            throw new UnbalancedJournalEntryException(context: ['total_debit' => (string) $debit, 'total_credit' => (string) $credit]);
        }

        $period ??= $this->fiscal->periodFor($date);
        $entry->forceFill([
            'fiscal_period_id' => $period->id,
            'entry_number' => $this->numbers->next('journal_entry', 'JE', $date),
            'status' => JournalEntryStatus::Posted,
            'posted_by' => $postedBy,
            'posted_at' => $this->clock->now(),
        ])->save();

        return $entry;
    }

    /** Open period; the year-end closing entry goes into its (closed, not locked) last period. */
    private function postingPeriod(CarbonInterface $date, JournalEntryType $type): FiscalPeriod
    {
        if ($type !== JournalEntryType::Closing) {
            return $this->fiscal->periodFor($date);
        }
        $period = $this->fiscal->periodContaining($date);
        if ($period->status === FiscalStatus::Locked) {
            throw new FiscalPeriodLockedException;
        }

        return $period;
    }

    /**
     * Mapping keys → account ids; every line one-sided on an active leaf account.
     *
     * @param  list<JournalLineData>  $lines
     * @return list<JournalLineData>
     */
    private function resolveLines(array $lines): array
    {
        $resolved = [];
        foreach ($lines as $i => $line) {
            $accountId = $line->account instanceof AccountMappingKey ? $this->accounts->resolve($line->account)->id : $line->account;
            if (! $line->isEmpty() && ! $line->isValid()) {
                throw new UnbalancedJournalEntryException(context: ['errors' => ["lines.{$i}" => [__('errors.JOURNAL_LINE_ONE_SIDE')]]]);
            }
            $account = Account::query()->find($accountId);
            if (! $account instanceof Account || ! $account->isPostable()) {
                throw new GroupAccountNotPostableException(context: ['errors' => ["lines.{$i}.account_id" => [__('errors.ACCOUNT_NOT_POSTABLE')]]]);
            }
            $resolved[] = new JournalLineData($accountId, $line->debit, $line->credit, $line->description, $line->departmentId, $line->patientId, $line->doctorId);
        }

        return $resolved;
    }

    /**
     * @param  list<JournalLineData>  $lines
     */
    private function writeLines(JournalEntry $entry, array $lines): void
    {
        $entry->lines()->delete();
        $debit = BigDecimal::zero();
        $credit = BigDecimal::zero();
        foreach ($lines as $i => $line) {
            $entry->lines()->create([
                'line_number' => $i + 1,
                'account_id' => $line->accountId(),
                'debit' => (string) $line->debit,
                'credit' => (string) $line->credit,
                'description' => $line->description,
                'department_id' => $line->departmentId,
                'patient_id' => $line->patientId,
                'doctor_id' => $line->doctorId,
            ]);
            $debit = $debit->plus($line->debit);
            $credit = $credit->plus($line->credit);
        }
        $entry->forceFill(['total_debit' => (string) $debit->toScale(3), 'total_credit' => (string) $credit->toScale(3)])->save();
    }
}
