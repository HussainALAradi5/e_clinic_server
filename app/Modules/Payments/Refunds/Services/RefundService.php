<?php

declare(strict_types=1);

namespace App\Modules\Payments\Refunds\Services;

use App\Enums\Accounting\AccountMappingKey;
use App\Modules\Accounting\Journal\Contracts\JournalEntryServiceInterface;
use App\Modules\Accounting\Journal\Data\AutomaticEntryData;
use App\Modules\Accounting\Journal\Data\JournalLineData;
use App\Modules\Payments\Invoices\Contracts\InvoiceServiceInterface;
use App\Modules\Payments\Payments\Models\Payment;
use App\Modules\Payments\Refunds\Contracts\RefundServiceInterface;
use App\Modules\Payments\Refunds\Data\RefundRequestData;
use App\Modules\Payments\Refunds\Events\RefundApproved;
use App\Modules\Payments\Refunds\Events\RefundProcessed;
use App\Modules\Payments\Refunds\Events\RefundRejected;
use App\Modules\Payments\Refunds\Events\RefundRequested;
use App\Modules\Payments\Refunds\Exceptions\RefundExceedsPaidAmountException;
use App\Modules\Payments\Refunds\Models\Refund;
use App\Modules\Transactions\CashSessions\Contracts\CashSessionServiceInterface;
use App\Shared\Access\OwnershipScope;
use App\Shared\Enums\ApprovalStatus;
use App\Shared\Enums\PaymentMethod;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Exceptions\SelfApprovalException;
use App\Shared\Query\ApplyListQuery;
use App\Shared\Services\BaseEntityService;
use App\Shared\Support\ClinicClock;
use App\Shared\Support\MoneyHelper;
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

            $payment = Payment::query()->lockForUpdate()->findOrFail($refund->payment_id);
            $payment->forceFill(['refunded_amount' => $payment->refunded_amount->plus($refund->amount)])->save();
            $this->invoices->applyRefund($refund->invoice, $refund->amount);

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
