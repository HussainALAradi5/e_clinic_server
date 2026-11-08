<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Dtos\Accounting\AutomaticEntryData;
use App\Dtos\Accounting\JournalLineData;
use App\Dtos\Payments\RefundRequestData;
use App\Enums\Accounting\AccountMappingKey;
use App\Enums\Shared\ApprovalStatus;
use App\Enums\Shared\PaymentMethod;
use App\Events\Payments\RefundApproved;
use App\Events\Payments\RefundProcessed;
use App\Events\Payments\RefundRejected;
use App\Events\Payments\RefundRequested;
use App\Exceptions\Payments\RefundExceedsPaidAmountException;
use App\Exceptions\Shared\InvalidStateTransitionException;
use App\Exceptions\Shared\SelfApprovalException;
use App\Interfaces\Accounting\JournalEntryServiceInterface;
use App\Interfaces\Payments\InvoiceServiceInterface;
use App\Interfaces\Payments\RefundServiceInterface;
use App\Interfaces\Payments\WalletServiceInterface;
use App\Interfaces\Transactions\CashSessionServiceInterface;
use App\Models\Payments\Payment;
use App\Models\Payments\Refund;
use App\Services\Base\BaseEntityService;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\MoneyHelper;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Refunds: requested at the desk, approved by a second person, then paid out by the cashier.
 * Processed refunds are final.
 *
 * @extends BaseEntityService<Refund>
 */
final class RefundService extends BaseEntityService implements RefundServiceInterface
{
    public const RELATIONS = ['invoice.patient.person', 'payment', 'requester.person'];

    public function __construct(
        ApplyListQuery $listQuery,
        private readonly InvoiceServiceInterface $invoices,
        private readonly CashSessionServiceInterface $cashSessions,
        private readonly JournalEntryServiceInterface $journal,
        private readonly ClinicClock $clock,
        private readonly WalletServiceInterface $wallet,
    ) {
        parent::__construct($listQuery);
    }

    public function find(string $id): Refund
    {
        return $this->query()->with(['approver.person', 'processor.person'])->findOrFail($id);
    }

    public function request(RefundRequestData $data): Refund
    {
        $refund = DB::transaction(function () use ($data): Refund {
            $payment = Payment::query()->lockForUpdate()->findOrFail($data->paymentId);
            if (! $payment->isCompleted() || $payment->method === PaymentMethod::Insurance) {
                throw new InvalidStateTransitionException(context: ['from' => $payment->status->value, 'to' => 'refunded']);
            }

            $amount = MoneyHelper::of($data->amount, $payment->currency);
            $committed = Refund::query()->where('payment_id', $payment->id)
                ->whereIn('status', [ApprovalStatus::Pending->value, ApprovalStatus::Approved->value])
                ->get()
                ->reduce(fn (Money $sum, Refund $r): Money => $sum->plus($r->amount), MoneyHelper::zero($payment->currency));
            $available = $payment->amount->minus($committed);
            if ($amount->isGreaterThan($available)) {
                $left = MoneyHelper::format($available);
                throw new RefundExceedsPaidAmountException(__('errors.REFUND_EXCEEDS_PAID', ['available' => $left]), ['available' => MoneyHelper::toDb($available), 'errors' => ['amount' => [__('errors.REFUND_EXCEEDS_PAID', ['available' => $left])]]]);
            }

            return Refund::query()->create([
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'amount' => $amount,
                'method' => $data->method ?? $payment->method,
                'reason' => $data->reason,
                'status' => ApprovalStatus::Pending,
                'requested_by' => Auth::id(),
            ]);
        });
        RefundRequested::dispatch($refund);

        return $this->find($refund->id);
    }

    public function approve(Refund $refund): Refund
    {
        $refund = $this->decide($refund, ApprovalStatus::Approved, ['approved_by' => Auth::id(), 'approved_at' => $this->clock->now()]);
        RefundApproved::dispatch($refund);

        return $refund;
    }

    public function reject(Refund $refund, string $reason): Refund
    {
        $refund = $this->decide($refund, ApprovalStatus::Rejected, ['rejection_reason' => $reason]);
        RefundRejected::dispatch($refund);

        return $refund;
    }

    public function process(Refund $refund, ?string $referenceNumber): Refund
    {
        $refund = DB::transaction(function () use ($refund, $referenceNumber): Refund {
            $refund = Refund::query()->with('invoice')->lockForUpdate()->findOrFail($refund->id);
            if ($refund->status !== ApprovalStatus::Approved || $refund->isProcessed()) {
                throw new InvalidStateTransitionException(context: ['from' => $refund->status->value, 'to' => 'processed']);
            }
            $payment = Payment::query()->lockForUpdate()->findOrFail($refund->payment_id);
            if (! $payment->isCompleted()) {
                throw new InvalidStateTransitionException(context: ['from' => $payment->status->value, 'to' => 'processed']);
            }
            $actor = Auth::user();
            $session = $refund->method === PaymentMethod::Cash && $actor !== null ? $this->cashSessions->requireOpen($actor) : null;

            /** @var array<string, AccountMappingKey> $accounts */
            $accounts = Config::array('accounting.payment_method_accounts');
            $patient = $refund->invoice->patient_id;
            $entry = $this->journal->postAutomatic(new AutomaticEntryData(
                $refund,
                $this->clock->today(),
                __('accounting.refund_processed', ['code' => $refund->code, 'payment' => $refund->payment()->value('code')]),
                [
                    JournalLineData::debit(AccountMappingKey::SalesReturns, $refund->amount, null, $patient),
                    JournalLineData::credit($accounts[$refund->method->value], $refund->amount, null, $patient),
                ],
            ));
            $refund->forceFill([
                'processed_by' => Auth::id(),
                'processed_at' => $this->clock->now(),
                'reference_number' => $referenceNumber,
                'cash_session_id' => $session?->id,
                'journal_entry_id' => $entry->id,
            ])->save();

            $payment->forceFill(['refunded_amount' => $payment->refunded_amount->plus($refund->amount)])->save();
            $this->invoices->applyRefund($refund->invoice, $refund->amount);
            $this->wallet->returnRefund($refund);

            return $refund;
        });
        RefundProcessed::dispatch($refund);

        return $this->find($refund->id);
    }

    /**
     * @return Builder<Refund>
     */
    protected function query(): Builder
    {
        return OwnershipScope::apply(Refund::query()->with(self::RELATIONS), 'refunds', Auth::user());
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function decide(Refund $refund, ApprovalStatus $to, array $values): Refund
    {
        return DB::transaction(function () use ($refund, $to, $values): Refund {
            $refund = Refund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($refund->status !== ApprovalStatus::Pending) {
                throw new InvalidStateTransitionException(context: ['from' => $refund->status->value, 'to' => $to->value]);
            }
            if ($refund->requested_by === Auth::id()) {
                throw new SelfApprovalException;
            }
            $refund->forceFill(['status' => $to, ...$values])->save();

            return $this->find($refund->id);
        });
    }
}
