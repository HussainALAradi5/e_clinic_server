<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Dtos\Accounting\AutomaticEntryData;
use App\Dtos\Accounting\JournalLineData;
use App\Dtos\Payments\RecordPaymentData;
use App\Dtos\Payments\WalletMovementData;
use App\Enums\Accounting\AccountMappingKey;
use App\Enums\Payments\WalletEntryType;
use App\Enums\Shared\PaymentMethod;
use App\Exceptions\Payments\InsufficientBalanceException;
use App\Exceptions\Payments\WalletLimitException;
use App\Exceptions\Shared\IdempotencyKeyConflictException;
use App\Interfaces\Accounting\JournalEntryServiceInterface;
use App\Interfaces\Payments\PaymentServiceInterface;
use App\Interfaces\Payments\WalletServiceInterface;
use App\Interfaces\Transactions\CashSessionServiceInterface;
use App\Models\Appointments\Patient;
use App\Models\Payments\Invoice;
use App\Models\Payments\PatientWalletEntry;
use App\Models\Payments\Payment;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Payments\InsuranceCoverage;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\MoneyHelper;
use App\Utilities\Shared\RoleLimit;
use Brick\Money\Money;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Patient balance: money the clinic holds for a patient (deposits, advance payments, payments returned). Every change
 * runs in one transaction with the patient row locked, writes a ledger entry with the balance after it, and posts to
 * the books against Patient deposits (2500). Rules (config payments.wallet):
 * - deposits: at least `min_deposit`, the balance never above `max_balance`, only `deposit_methods`; cash needs the
 *   receiver's open drawer; a retried request (same idempotency key) returns the first entry.
 * - pay-backs: only `withdraw_methods`, a written reason, never more than the balance (cash from the open drawer).
 * - adjustments: a written reason, within the role's `adjustment_limits`; a debit never below zero.
 * - invoices paid from the balance: never more than the balance or what is due; voiding such a payment returns it.
 */
final class WalletService implements WalletServiceInterface
{
    public function __construct(
        private readonly JournalEntryServiceInterface $journal,
        private readonly PaymentServiceInterface $payments,
        private readonly CashSessionServiceInterface $cashSessions,
        private readonly ApplyListQuery $listQuery,
        private readonly ClinicClock $clock,
    ) {}

    public function balance(Patient $patient): Money
    {
        return MoneyHelper::of((string) Patient::query()->whereKey($patient->id)->toBase()->value('wallet_balance'));
    }

    public function entries(Patient $patient, ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        $builder = OwnershipScope::apply(PatientWalletEntry::query()->where('patient_id', $patient->id), 'wallet', Auth::user());

        return $this->listQuery->paginate($builder, $query);
    }

    public function deposit(Patient $patient, WalletMovementData $data, string $idempotencyKey): PatientWalletEntry
    {
        $amount = MoneyHelper::of($data->amount);
        $this->assertMethod($data->method, 'deposit_methods');
        if ($amount->isLessThan(MoneyHelper::of(Config::string('payments.wallet.min_deposit')))) {
            throw new WalletLimitException(__('errors.WALLET_MIN_DEPOSIT', ['min' => MoneyHelper::format(MoneyHelper::of(Config::string('payments.wallet.min_deposit')))]));
        }

        return $this->once($idempotencyKey, $patient, WalletEntryType::Deposit, $amount, function (Patient $locked, Money $balance) use ($amount, $data, $idempotencyKey): PatientWalletEntry {
            $max = MoneyHelper::of(Config::string('payments.wallet.max_balance'));
            if ($balance->plus($amount)->isGreaterThan($max)) {
                throw new WalletLimitException(__('errors.WALLET_MAX_BALANCE', ['max' => MoneyHelper::format($max)]));
            }
            $session = $this->drawerFor($data->method);

            return $this->record($locked, WalletEntryType::Deposit, $amount, $balance->plus($amount), [
                'method' => $data->method, 'reference_number' => $data->referenceNumber, 'reason' => $data->reason,
                'cash_session_id' => $session, 'idempotency_key' => $idempotencyKey,
            ], [
                JournalLineData::debit($this->methodAccount($data->method), $amount, null, $locked->id),
                JournalLineData::credit(AccountMappingKey::PatientDeposits, $amount, null, $locked->id),
            ]);
        });
    }

    public function withdraw(Patient $patient, WalletMovementData $data, string $idempotencyKey): PatientWalletEntry
    {
        $amount = MoneyHelper::of($data->amount);
        $this->assertMethod($data->method, 'withdraw_methods');
        $this->assertReason($data->reason);

        return $this->once($idempotencyKey, $patient, WalletEntryType::Withdrawal, $amount, function (Patient $locked, Money $balance) use ($amount, $data, $idempotencyKey): PatientWalletEntry {
            $this->assertCovers($balance, $amount);

            return $this->record($locked, WalletEntryType::Withdrawal, $amount, $balance->minus($amount), [
                'method' => $data->method, 'reference_number' => $data->referenceNumber, 'reason' => $data->reason,
                'cash_session_id' => $this->drawerFor($data->method), 'idempotency_key' => $idempotencyKey,
            ], [
                JournalLineData::debit(AccountMappingKey::PatientDeposits, $amount, null, $locked->id),
                JournalLineData::credit($this->methodAccount($data->method), $amount, null, $locked->id),
            ]);
        });
    }

    public function adjust(Patient $patient, WalletMovementData $data, bool $credit): PatientWalletEntry
    {
        $amount = MoneyHelper::of($data->amount);
        $this->assertReason($data->reason);
        $limit = RoleLimit::best(Auth::user(), Config::array('payments.wallet.adjustment_limits'));
        if ($limit !== null && $amount->getAmount()->isGreaterThan($limit)) {
            throw new WalletLimitException(__('errors.WALLET_ADJUSTMENT_LIMIT', ['max' => MoneyHelper::format(MoneyHelper::of($limit))]));
        }
        $type = $credit ? WalletEntryType::AdjustmentCredit : WalletEntryType::AdjustmentDebit;

        return DB::transaction(function () use ($patient, $amount, $data, $credit, $type): PatientWalletEntry {
            [$locked, $balance] = $this->lock($patient);
            if (! $credit) {
                $this->assertCovers($balance, $amount);
            }
            $lines = $credit
                ? [JournalLineData::debit(AccountMappingKey::SalesReturns, $amount, null, $locked->id), JournalLineData::credit(AccountMappingKey::PatientDeposits, $amount, null, $locked->id)]
                : [JournalLineData::debit(AccountMappingKey::PatientDeposits, $amount, null, $locked->id), JournalLineData::credit(AccountMappingKey::SalesReturns, $amount, null, $locked->id)];

            return $this->record($locked, $type, $amount, $credit ? $balance->plus($amount) : $balance->minus($amount), ['reason' => $data->reason], $lines);
        });
    }

    public function payInvoice(Invoice $invoice, ?string $amount, string $idempotencyKey): Payment
    {
        return DB::transaction(function () use ($invoice, $amount, $idempotencyKey): Payment {
            $previous = PatientWalletEntry::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($previous?->payment_id !== null) {
                return Payment::query()->findOrFail($previous->payment_id);
            }
            [$locked, $balance] = $this->lock($invoice->patient()->firstOrFail());
            $invoice->refresh();
            // By default: what the patient owes (their co-pay on an insured invoice), within the balance.
            $split = InsuranceCoverage::split($invoice, $this->clock->today());
            $owed = $split->covered ? $split->patientDue : $invoice->balance_due;
            $paid = $amount !== null ? MoneyHelper::of($amount, $invoice->currency) : ($balance->isLessThan($owed) ? $balance : $owed);
            if ($paid->isNegativeOrZero()) {
                throw new InsufficientBalanceException;
            }
            $this->assertCovers($balance, $paid);

            // The payment posts Dr Patient deposits / Cr Accounts receivable and applies to the invoice.
            $payment = $this->payments->record(new RecordPaymentData($invoice->id, MoneyHelper::toDb($paid), PaymentMethod::PatientBalance), 'wallet:'.$idempotencyKey);
            $this->record($locked, WalletEntryType::InvoicePayment, $paid, $balance->minus($paid), [
                'payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'journal_entry_id' => $payment->journal_entry_id,
                'idempotency_key' => $idempotencyKey,
            ]);

            return $payment;
        });
    }

    public function returnVoided(Payment $payment): void
    {
        if ($payment->method !== PaymentMethod::PatientBalance) {
            return;
        }
        DB::transaction(function () use ($payment): void {
            [$locked, $balance] = $this->lock($payment->patient()->firstOrFail());
            // The payment's own journal entry was reversed by the void, which already credits Patient deposits back.
            $this->record($locked, WalletEntryType::PaymentReversal, $payment->amount, $balance->plus($payment->amount), [
                'payment_id' => $payment->id, 'invoice_id' => $payment->invoice_id, 'reason' => $payment->void_reason,
            ]);
        });
    }

    /**
     * Runs a keyed movement once: a retry with the same key returns the first entry (or fails if it differs).
     *
     * @param  callable(Patient, Money): PatientWalletEntry  $move
     */
    private function once(string $key, Patient $patient, WalletEntryType $type, Money $amount, callable $move): PatientWalletEntry
    {
        return DB::transaction(function () use ($key, $patient, $type, $amount, $move): PatientWalletEntry {
            $previous = PatientWalletEntry::query()->where('idempotency_key', $key)->first();
            if ($previous !== null) {
                if ($previous->patient_id !== $patient->id || $previous->type !== $type || ! $previous->amount->isEqualTo($amount)) {
                    throw new IdempotencyKeyConflictException;
                }

                return $previous;
            }
            [$locked, $balance] = $this->lock($patient);

            return $move($locked, $balance);
        });
    }

    /** @return array{0: Patient, 1: Money} the patient row locked for this transaction and its balance */
    private function lock(Patient $patient): array
    {
        $locked = Patient::query()->lockForUpdate()->findOrFail($patient->id);

        return [$locked, MoneyHelper::of((string) $locked->getRawOriginal('wallet_balance'))];
    }

    /**
     * Writes the ledger entry, posts its journal lines (when given) and stores the new balance.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<JournalLineData>  $lines
     */
    private function record(Patient $patient, WalletEntryType $type, Money $amount, Money $after, array $attributes, array $lines = []): PatientWalletEntry
    {
        $entry = new PatientWalletEntry([
            'patient_id' => $patient->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $after,
            'currency' => $amount->getCurrency()->getCurrencyCode(),
            ...$attributes,
        ]);
        $entry->save();
        if ($lines !== []) {
            $journal = $this->journal->postAutomatic(new AutomaticEntryData($entry, $this->clock->today(), __('accounting.wallet_'.$type->value, ['code' => $entry->code]), $lines));
            $entry->forceFill(['journal_entry_id' => $journal->id])->save();
        }
        Patient::query()->whereKey($patient->id)->update(['wallet_balance' => MoneyHelper::toDb($after)]);

        return $entry->refresh();
    }

    private function assertCovers(Money $balance, Money $amount): void
    {
        if ($amount->isGreaterThan($balance)) {
            throw new InsufficientBalanceException(__('errors.WALLET_INSUFFICIENT', ['balance' => MoneyHelper::format($balance)]));
        }
    }

    private function assertMethod(?PaymentMethod $method, string $allowed): void
    {
        if ($method === null || ! in_array($method->value, Config::array("payments.wallet.{$allowed}"), true)) {
            throw new WalletLimitException(__('errors.WALLET_METHOD'));
        }
    }

    private function assertReason(?string $reason): void
    {
        if (mb_strlen(trim((string) $reason)) < Config::integer('payments.wallet.reason_min_length')) {
            throw new WalletLimitException(__('errors.WALLET_REASON', ['min' => Config::integer('payments.wallet.reason_min_length')]));
        }
    }

    /** The receiver's open drawer for cash; null for other methods. */
    private function drawerFor(?PaymentMethod $method): ?string
    {
        $actor = Auth::user();

        return $method === PaymentMethod::Cash && $actor !== null ? (string) $this->cashSessions->requireOpen($actor)->id : null;
    }

    private function methodAccount(?PaymentMethod $method): AccountMappingKey
    {
        /** @var array<string, AccountMappingKey> $map */
        $map = Config::array('accounting.payment_method_accounts');

        return $map[$method?->value ?? PaymentMethod::Cash->value];
    }
}
