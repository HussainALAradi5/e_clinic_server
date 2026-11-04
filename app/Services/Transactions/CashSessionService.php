<?php

declare(strict_types=1);

namespace App\Services\Transactions;

use App\Dtos\Accounting\AutomaticEntryData;
use App\Dtos\Accounting\JournalLineData;
use App\Dtos\Transactions\CashSessionReport;
use App\Dtos\Transactions\CloseSessionData;
use App\Dtos\Transactions\DepositData;
use App\Enums\Accounting\AccountMappingKey;
use App\Enums\Payments\PaymentStatus;
use App\Enums\Payments\WalletEntryType;
use App\Enums\Shared\PaymentMethod;
use App\Enums\Transactions\CashSessionStatus;
use App\Exceptions\Shared\InvalidStateTransitionException;
use App\Exceptions\Shared\SelfApprovalException;
use App\Exceptions\Transactions\CashSessionAlreadyOpenException;
use App\Exceptions\Transactions\CashSessionNotOpenException;
use App\Exceptions\Transactions\DepositExceedsCashException;
use App\Exceptions\Transactions\NoOpenCashSessionException;
use App\Interfaces\Accounting\JournalEntryServiceInterface;
use App\Interfaces\Transactions\CashSessionServiceInterface;
use App\Models\Payments\PatientWalletEntry;
use App\Models\Payments\Payment;
use App\Models\Transactions\CashSession;
use App\Services\Base\BaseEntityService;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\MoneyHelper;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Cash drawer shifts. Every cash payment, refund and expense is tied to the open session of whoever handles the
 * cash; at close the count is reconciled against the expected cash computed from those documents.
 *
 * @extends BaseEntityService<CashSession>
 */
final class CashSessionService extends BaseEntityService implements CashSessionServiceInterface
{
    public function __construct(
        ApplyListQuery $listQuery,
        private readonly JournalEntryServiceInterface $journal,
        private readonly ClinicClock $clock,
    ) {
        parent::__construct($listQuery);
    }

    public function find(string $id): CashSession
    {
        return $this->query()->with('approver.person')->findOrFail($id);
    }

    public function current(Authenticatable $cashier): ?CashSession
    {
        return CashSession::query()->with('cashier.person')
            ->where('cashier_id', $cashier->getAuthIdentifier())
            ->whereIn('status', [CashSessionStatus::Open->value, CashSessionStatus::Closing->value])
            ->first();
    }

    public function requireOpen(Authenticatable $cashier): CashSession
    {
        $session = CashSession::query()
            ->where('cashier_id', $cashier->getAuthIdentifier())
            ->where('status', CashSessionStatus::Open->value)
            ->lockForUpdate()
            ->first();

        return $session ?? throw new NoOpenCashSessionException;
    }

    public function open(Authenticatable $cashier, Money $openingFloat): CashSession
    {
        return DB::transaction(function () use ($cashier, $openingFloat): CashSession {
            if ($this->current($cashier) instanceof CashSession) {
                throw new CashSessionAlreadyOpenException;
            }
            $session = CashSession::query()->create([
                'cashier_id' => $cashier->getAuthIdentifier(),
                'opened_at' => $this->clock->now(),
                'opening_float' => $openingFloat,
                'status' => CashSessionStatus::Open,
            ]);

            return $this->find($session->id);
        });
    }

    public function close(CashSession $session, CloseSessionData $data): CashSession
    {
        return DB::transaction(function () use ($session, $data): CashSession {
            $session = CashSession::query()->lockForUpdate()->findOrFail($session->id);
            if (! $session->isOpen()) {
                throw new CashSessionNotOpenException;
            }

            $expected = $this->report($session)->expected;
            $counted = MoneyHelper::of($data->countedClosing);
            $difference = $counted->minus($expected);
            $withinTolerance = $difference->abs()->isLessThanOrEqualTo(MoneyHelper::of(Config::string('payments.cash_tolerance')));

            $session->forceFill([
                'expected_closing' => $expected,
                'counted_closing' => $counted,
                'difference' => $difference,
                'denominations' => $data->denominations,
                'notes' => $data->notes,
                'closed_at' => $this->clock->now(),
                'status' => $withinTolerance ? CashSessionStatus::Closed : CashSessionStatus::Closing,
            ]);
            if ($withinTolerance) {
                $session->adjustment_journal_entry_id = $this->postOverShort($session, $difference);
            }
            $session->save();

            return $this->find($session->id);
        });
    }

    public function approveDifference(CashSession $session, string $notes): CashSession
    {
        return DB::transaction(function () use ($session, $notes): CashSession {
            $session = CashSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($session->status !== CashSessionStatus::Closing) {
                throw new InvalidStateTransitionException(context: ['from' => $session->status->value, 'to' => CashSessionStatus::Closed->value]);
            }
            if ($session->cashier_id === Auth::id()) {
                throw new SelfApprovalException;
            }

            $session->forceFill([
                'status' => CashSessionStatus::Closed,
                'approved_by' => Auth::id(),
                'notes' => trim(($session->notes ?? '')."\n".$notes),
                'adjustment_journal_entry_id' => $this->postOverShort($session, $session->difference ?? MoneyHelper::zero()),
            ])->save();

            return $this->find($session->id);
        });
    }

    public function deposit(CashSession $session, DepositData $data): CashSession
    {
        return DB::transaction(function () use ($session, $data): CashSession {
            $session = CashSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($session->status !== CashSessionStatus::Closed || $session->deposited_at !== null) {
                throw new InvalidStateTransitionException(context: ['from' => $session->status->value, 'to' => 'deposited']);
            }
            $amount = MoneyHelper::of($data->amount);
            if ($amount->isGreaterThan($session->counted_closing ?? MoneyHelper::zero())) {
                throw new DepositExceedsCashException(context: ['errors' => ['amount' => [__('errors.DEPOSIT_EXCEEDS_CASH')]]]);
            }

            $entry = $this->journal->postAutomatic(new AutomaticEntryData(
                $session,
                $data->date,
                __('accounting.cash_deposit', ['code' => $session->code, 'reference' => $data->reference]),
                [JournalLineData::debit(AccountMappingKey::Bank, $amount), JournalLineData::credit(AccountMappingKey::CashOnHand, $amount)],
            ));
            $session->forceFill([
                'deposit_amount' => $amount,
                'deposited_at' => $data->date,
                'deposit_reference' => $data->reference,
                'deposit_journal_entry_id' => $entry->id,
            ])->save();

            return $this->find($session->id);
        });
    }

    public function report(CashSession $session): CashSessionReport
    {
        $currency = MoneyHelper::defaultCurrency();
        $sum = fn (HasMany $documents): Money => MoneyHelper::of($this->decimal($documents->sum('amount')), $currency);

        $cashIn = $sum($session->payments()->where('status', PaymentStatus::Completed->value)->where('method', PaymentMethod::Cash->value));
        $refunds = $sum($session->refunds()->whereNotNull('processed_at')->where('method', PaymentMethod::Cash->value));
        $expenses = $sum($session->expenses()->whereNotNull('paid_at')->where('payment_method', PaymentMethod::Cash->value));
        $balance = fn (WalletEntryType $type): Money => MoneyHelper::of($this->decimal(PatientWalletEntry::query()
            ->where('cash_session_id', $session->id)->where('type', $type->value)->sum('amount')), $currency);
        $deposits = $balance(WalletEntryType::Deposit);
        $paybacks = $balance(WalletEntryType::Withdrawal);

        // Everything the cashier took during the shift, any method (Z-report).
        $taken = Payment::query()
            ->where('received_by', $session->cashier_id)
            ->where('status', PaymentStatus::Completed->value)
            ->where('paid_at', '>=', $session->opened_at)
            ->when($session->closed_at !== null, fn (Builder $q) => $q->where('paid_at', '<=', $session->closed_at))
            ->groupBy('method')
            ->orderBy('method')
            ->selectRaw('method, sum(amount) as total')
            ->pluck('total', 'method');
        $byMethod = [];
        foreach ($taken as $method => $total) {
            $byMethod[(string) $method] = MoneyHelper::of($this->decimal($total), $currency);
        }

        return new CashSessionReport(
            openingFloat: $session->opening_float,
            cashIn: $cashIn,
            cashRefunds: $refunds,
            cashExpenses: $expenses,
            balanceDeposits: $deposits,
            balancePaybacks: $paybacks,
            expected: $session->opening_float->plus($cashIn)->plus($deposits)->minus($refunds)->minus($expenses)->minus($paybacks),
            byMethod: $byMethod,
            payments: $session->payments()->where('status', PaymentStatus::Completed->value)->count(),
            refunds: $session->refunds()->whereNotNull('processed_at')->count(),
            expenses: $session->expenses()->whereNotNull('paid_at')->count(),
        );
    }

    /**
     * @return Builder<CashSession>
     */
    protected function query(): Builder
    {
        return OwnershipScope::apply(CashSession::query()->with('cashier.person'), 'cash_sessions', Auth::user());
    }

    /** Over (counted > expected): Dr cash / Cr over-short; short: the opposite. Null when exact. */
    private function postOverShort(CashSession $session, Money $difference): ?string
    {
        if ($difference->isZero()) {
            return null;
        }
        $amount = $difference->abs();
        $lines = $difference->isPositive()
            ? [JournalLineData::debit(AccountMappingKey::CashOnHand, $amount), JournalLineData::credit(AccountMappingKey::CashOverShort, $amount)]
            : [JournalLineData::debit(AccountMappingKey::CashOverShort, $amount), JournalLineData::credit(AccountMappingKey::CashOnHand, $amount)];

        return $this->journal->postAutomatic(new AutomaticEntryData(
            $session,
            $this->clock->today(),
            __('accounting.cash_over_short', ['code' => $session->code]),
            $lines,
        ))->id;
    }

    /** SQL sums arrive as strings (PostgreSQL) or floats (SQLite); never do arithmetic on the float. */
    private function decimal(mixed $value): string
    {
        return is_numeric($value) ? (string) BigDecimal::of((string) $value)->toScale(3, RoundingMode::HALF_UP) : '0';
    }
}
