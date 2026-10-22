<?php

declare(strict_types=1);

namespace App\Modules\Payments\Payments\Services;

use App\Enums\Accounting\AccountMappingKey;
use App\Enums\Payments\PaymentStatus;
use App\Modules\Accounting\Journal\Contracts\JournalEntryServiceInterface;
use App\Modules\Accounting\Journal\Data\AutomaticEntryData;
use App\Modules\Accounting\Journal\Data\JournalLineData;
use App\Modules\Accounting\Journal\Models\JournalEntry;
use App\Modules\Payments\Invoices\Contracts\InvoiceServiceInterface;
use App\Modules\Payments\Invoices\Models\Invoice;
use App\Modules\Payments\Payments\Contracts\PaymentServiceInterface;
use App\Modules\Payments\Payments\Data\RecordPaymentData;
use App\Modules\Payments\Payments\Events\PaymentReceived;
use App\Modules\Payments\Payments\Events\PaymentVoided;
use App\Modules\Payments\Payments\Exceptions\InvoiceNotPayableException;
use App\Modules\Payments\Payments\Exceptions\OverpaymentException;
use App\Modules\Payments\Payments\Exceptions\PaymentNotVoidableException;
use App\Modules\Payments\Payments\Models\Payment;
use App\Modules\Transactions\CashSessions\Contracts\CashSessionServiceInterface;
use App\Modules\Transactions\CashSessions\Models\CashSession;
use App\Shared\Access\OwnershipScope;
use App\Shared\Enums\PaymentMethod;
use App\Shared\Exceptions\IdempotencyKeyConflictException;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Services\BaseEntityService;
use App\Shared\Support\ClinicClock;
use App\Shared\Support\MoneyHelper;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Money received against invoices. Each payment posts Dr cash / card clearing / bank / insurance receivable
 * (by method) / Cr accounts receivable; cash needs the cashier's open drawer.
 *
 * @extends BaseEntityService<Payment>
 */
final class PaymentService extends BaseEntityService implements PaymentServiceInterface
{
    public const RELATIONS = ['patient.person', 'invoice', 'receiver.person'];

    public function __construct(
        ApplyListQuery $listQuery,
        private readonly InvoiceServiceInterface $invoices,
        private readonly CashSessionServiceInterface $cashSessions,
        private readonly JournalEntryServiceInterface $journal,
        private readonly ClinicClock $clock,
    ) {
        parent::__construct($listQuery);
    }

    public function find(string $id): Payment
    {
        return $this->query()->with(['refunds', 'cashSession'])->findOrFail($id);
    }

    public function record(RecordPaymentData $data, string $idempotencyKey): Payment
    {
        $payment = DB::transaction(function () use ($data, $idempotencyKey): Payment {
            $previous = Payment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($previous instanceof Payment) {
                $same = $previous->invoice_id === $data->invoiceId && $previous->method === $data->method
                    && $previous->amount->isEqualTo(MoneyHelper::of($data->amount, $previous->currency));
                if (! $same) {
                    throw new IdempotencyKeyConflictException;
                }

                return $previous;
            }

            $invoice = Invoice::query()->lockForUpdate()->findOrFail($data->invoiceId);
            if (! $invoice->status->isPayable()) {
                throw new InvoiceNotPayableException;
            }
            $amount = MoneyHelper::of($data->amount, $invoice->currency);
            if ($amount->isGreaterThan($invoice->balance_due)) {
                $balance = MoneyHelper::format($invoice->balance_due);
                throw new OverpaymentException(__('errors.PAYMENT_EXCEEDS_BALANCE', ['balance' => $balance]), ['balance_due' => MoneyHelper::toDb($invoice->balance_due), 'errors' => ['amount' => [__('errors.PAYMENT_EXCEEDS_BALANCE', ['balance' => $balance])]]]);
            }
            $actor = Auth::user();
            $session = $data->method === PaymentMethod::Cash && $actor !== null ? $this->cashSessions->requireOpen($actor) : null;

            $payment = Payment::query()->create([
                'invoice_id' => $invoice->id,
                'patient_id' => $invoice->patient_id,
                'amount' => $amount,
                'currency' => $invoice->currency,
                'method' => $data->method,
                'status' => PaymentStatus::Completed,
                'reference_number' => $data->referenceNumber,
                'card_last4' => $data->cardLast4,
                'paid_at' => $this->clock->now(),
                'received_by' => Auth::id(),
                'cash_session_id' => $session instanceof CashSession ? $session->id : null,
                'idempotency_key' => $idempotencyKey,
                'notes' => $data->notes,
            ]);

            $entry = $this->journal->postAutomatic(new AutomaticEntryData(
                $payment,
                $this->clock->today(),
                __('accounting.payment_received', ['code' => $payment->code, 'invoice' => $invoice->invoice_number]),
                [
                    JournalLineData::debit($this->methodAccount($data->method), $amount, null, $invoice->patient_id),
                    JournalLineData::credit(AccountMappingKey::AccountsReceivable, $amount, null, $invoice->patient_id),
                ],
            ));
            $payment->forceFill(['journal_entry_id' => $entry->id])->save();
            $this->invoices->applyPayment($invoice, $amount);

            return $payment;
        });
        if ($payment->wasRecentlyCreated) {
            PaymentReceived::dispatch($payment);
        }

        return $this->find($payment->id);
    }

    public function void(Payment $payment, string $reason): Payment
    {
        $payment = DB::transaction(function () use ($payment, $reason): Payment {
            $payment = Payment::query()->with('cashSession')->lockForUpdate()->findOrFail($payment->id);
            $sameDay = $this->clock->toClinic($payment->paid_at)->isSameDay($this->clock->toClinic($this->clock->now()));
            $drawerOpen = $payment->cash_session_id === null || ($payment->cashSession?->isOpen() ?? false);
            $voidable = $payment->isCompleted() && $sameDay && $payment->refunded_amount->isZero() && $drawerOpen
                && $payment->method !== PaymentMethod::Insurance;
            if (! $voidable) {
                throw new PaymentNotVoidableException;
            }

            if ($payment->journal_entry_id !== null) {
                $this->journal->reverse(JournalEntry::query()->findOrFail($payment->journal_entry_id), $this->clock->today(), $reason, fromSource: true);
            }
            $payment->forceFill(['status' => PaymentStatus::Voided, 'voided_at' => $this->clock->now(), 'void_reason' => $reason])->save();
            $this->invoices->applyPayment($payment->invoice()->firstOrFail(), $payment->amount->negated());

            return $payment;
        });
        PaymentVoided::dispatch($payment);

        return $this->find($payment->id);
    }

    public function dailySummary(CarbonImmutable $date, ?string $cashierId = null): array
    {
        [$from, $to] = $this->clock->dayRangeUtc($date, $date);
        $rows = $this->query()
            ->where('payments.status', PaymentStatus::Completed->value)
            ->whereBetween('paid_at', [$from, $to])
            ->when($cashierId !== null, fn (Builder $q) => $q->where('received_by', $cashierId))
            ->get()
            ->groupBy(fn (Payment $p): string => $p->method->value.'|'.$p->received_by)
            ->map(function ($group): array {
                /** @var Payment $first */
                $first = $group->first();
                $total = $group->reduce(fn ($sum, Payment $p) => $sum->plus($p->amount), MoneyHelper::zero());

                return [
                    'method' => $first->method->value,
                    'received_by' => $first->received_by,
                    'cashier' => $first->receiver->person->full_name,
                    'count' => $group->count(),
                    'total' => MoneyHelper::toDb($total),
                ];
            })
            ->sortBy(['method', 'cashier'])
            ->values()
            ->all();

        $totals = [];
        foreach ($rows as $row) {
            $totals[$row['method']] = (string) BigDecimal::of($totals[$row['method']] ?? '0')->plus($row['total'])->toScale(3, RoundingMode::HALF_UP);
        }
        $refunds = DB::table('refunds')
            ->where('is_deleted', false)
            ->whereBetween('processed_at', [$from, $to])
            ->when($cashierId !== null, fn ($q) => $q->where('processed_by', $cashierId))
            ->sum('amount');

        return [
            'date' => $date->toDateString(),
            'rows' => array_values($rows),
            'totals' => $totals,
            'grand_total' => (string) array_reduce(array_values($totals), fn (BigDecimal $s, string $v): BigDecimal => $s->plus($v), BigDecimal::zero())->toScale(3),
            'refunds' => (string) BigDecimal::of((string) $refunds)->toScale(3, RoundingMode::HALF_UP),
        ];
    }

    /**
     * @return Builder<Payment>
     */
    protected function query(): Builder
    {
        return OwnershipScope::apply(Payment::query()->with(self::RELATIONS), 'payments', Auth::user());
    }

    private function methodAccount(PaymentMethod $method): AccountMappingKey
    {
        /** @var array<string, AccountMappingKey> $map */
        $map = Config::array('accounting.payment_method_accounts');

        return $map[$method->value];
    }
}
