<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Dtos\Payments\GatewayCharge;
use App\Dtos\Payments\GatewayChargeRequest;
use App\Dtos\Payments\RecordPaymentData;
use App\Enums\Payments\OnlinePaymentStatus;
use App\Enums\Shared\PaymentMethod;
use App\Exceptions\Payments\InvoiceNotPayableException;
use App\Exceptions\Payments\OnlinePaymentAmountException;
use App\Exceptions\Payments\OnlinePaymentsDisabledException;
use App\Exceptions\Payments\OnlinePaymentUnavailableException;
use App\Interfaces\Payments\OnlinePaymentServiceInterface;
use App\Interfaces\Payments\PaymentGatewayInterface;
use App\Interfaces\Payments\PaymentServiceInterface;
use App\Interfaces\Payments\WalletServiceInterface;
use App\Interfaces\Reference\ClinicProfileServiceInterface;
use App\Models\Payments\Invoice;
use App\Models\Payments\OnlinePayment;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Payments\InsuranceCoverage;
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

final class OnlinePaymentService implements OnlinePaymentServiceInterface
{
    public const RELATIONS = ['invoice', 'patient.person', 'payment'];

    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly PaymentServiceInterface $payments,
        private readonly WalletServiceInterface $wallet,
        private readonly ApplyListQuery $listQuery,
        private readonly ClinicClock $clock,
        private readonly ClinicProfileServiceInterface $clinic,
    ) {}

    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate($this->query(), $query);
    }

    public function find(string $id): OnlinePayment
    {
        return $this->query()->findOrFail($id);
    }

    public function start(Invoice $invoice, ?string $amount, string $idempotencyKey): OnlinePayment
    {
        $previous = OnlinePayment::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($previous !== null) {
            return $this->find($previous->id);
        }
        $settings = $this->clinic->onlinePayments();
        if (! $settings->enabled) {
            throw new OnlinePaymentsDisabledException;
        }
        if (! $invoice->status->isPayable()) {
            throw new InvoiceNotPayableException;
        }
        $split = InsuranceCoverage::split($invoice, $this->clock->today());
        $owed = $split->covered ? $split->patientDue : $invoice->balance_due;
        $paid = $amount === null ? $owed : MoneyHelper::of($amount, $invoice->currency);
        $min = MoneyHelper::of(Config::string('payments.online.min_amount'), $invoice->currency);
        if ($paid->isLessThan($min) || $paid->isGreaterThan($owed)) {
            throw new OnlinePaymentAmountException(__('errors.ONLINE_PAYMENT_AMOUNT', ['min' => MoneyHelper::format($min), 'max' => MoneyHelper::format($owed)]), [
                'max' => MoneyHelper::toDb($owed),
            ]);
        }

        $online = OnlinePayment::query()->create([
            'invoice_id' => $invoice->id,
            'patient_id' => $invoice->patient_id,
            'amount' => $paid,
            'currency' => $invoice->currency,
            'status' => OnlinePaymentStatus::Initiated,
            'provider' => $settings->driver,
            'started_by' => Auth::id(),
            'idempotency_key' => $idempotencyKey,
            'expires_at' => $this->clock->now()->addMinutes(Config::integer('payments.online.expiry_minutes')),
        ]);
        $person = $invoice->patient->person;
        try {
            $charge = $this->gateway->createCharge(new GatewayChargeRequest(
                amount: MoneyHelper::toDb($paid),
                currency: $invoice->currency,
                reference: $online->code,
                description: __('accounting.online_charge', ['invoice' => $invoice->invoice_number ?? $invoice->code]),
                firstName: $person->first_name,
                lastName: $person->last_name,
                email: $person->email,
                phone: $person->phone,
                redirectUrl: route('online-payments.return'),
                webhookUrl: route('webhooks.tap'),
            ));
        } catch (OnlinePaymentUnavailableException $e) {
            $online->update(['status' => OnlinePaymentStatus::Failed, 'failure_reason' => 'gateway_unavailable']);

            throw $e;
        }
        $online->update(['charge_id' => $charge->id, 'checkout_url' => $charge->checkoutUrl, 'gateway_status' => $charge->status]);

        return $this->find($online->id);
    }

    public function sync(string $chargeId): ?OnlinePayment
    {
        $known = OnlinePayment::query()->where('charge_id', $chargeId)->first();
        if ($known === null || $known->status->isFinal()) {
            return $known;
        }
        // The gateway's own record decides; nothing in a webhook body or a return URL is trusted.
        $charge = $this->gateway->retrieveCharge($chargeId);

        return DB::transaction(function () use ($known, $charge): OnlinePayment {
            $online = OnlinePayment::query()->with(['invoice', 'patient'])->lockForUpdate()->findOrFail($known->id);
            if ($online->status->isFinal()) {
                return $online;
            }
            $online->gateway_status = $charge->status;
            if ($charge->isCaptured()) {
                $this->book($online, $charge);
            } elseif ($charge->isFailed()) {
                $online->fill(['status' => OnlinePaymentStatus::Failed, 'failure_reason' => $charge->failureReason ?? strtolower($charge->status)]);
            }
            $online->save();

            return $online;
        });
    }

    public function expireStale(): int
    {
        $stale = OnlinePayment::query()
            ->where('status', OnlinePaymentStatus::Initiated->value)
            ->where('expires_at', '<', $this->clock->now())
            ->get();
        $expired = 0;
        foreach ($stale as $online) {
            // One last look: a payer may have finished just before the webhook got through.
            $synced = $online->charge_id === null ? $online : rescue(fn () => $this->sync($online->charge_id), $online, report: true);
            if ($synced !== null && ! $synced->status->isFinal()) {
                $synced->update(['status' => OnlinePaymentStatus::Expired]);
                $expired++;
            }
        }

        return $expired;
    }

    /** Captured: the amount and currency must be the ones asked for; the invoice gets what it still owes, the balance any surplus. */
    private function book(OnlinePayment $online, GatewayCharge $charge): void
    {
        $captured = MoneyHelper::of($charge->amount, $online->currency);
        if ($charge->currency !== $online->currency || ! $captured->isEqualTo($online->amount)) {
            report(new \RuntimeException("Online payment {$online->code}: gateway captured {$charge->amount} {$charge->currency}, expected ".MoneyHelper::toDb($online->amount)));
            $online->fill(['status' => OnlinePaymentStatus::Failed, 'failure_reason' => 'amount_mismatch']);

            return;
        }
        $invoice = $online->invoice->refresh();
        $toInvoice = $invoice->status->isPayable() ? $this->smaller($captured, $invoice->balance_due) : MoneyHelper::zero($online->currency);
        if ($toInvoice->isPositive()) {
            $payment = $this->payments->record(new RecordPaymentData(
                invoiceId: $invoice->id,
                amount: MoneyHelper::toDb($toInvoice),
                method: PaymentMethod::Online,
                referenceNumber: $charge->id,
                notes: $online->code,
                // The amount was checked against the co-pay when the payment started.
                payInsurerShare: true,
                receivedBy: $online->started_by,
            ), 'online:'.$online->id);
            $online->payment_id = $payment->id;
        }
        $surplus = $captured->minus($toInvoice);
        if ($surplus->isPositive()) {
            $this->wallet->creditOnlineSurplus($online->patient, $surplus, $charge->id, 'online-surplus:'.$online->id);
        }
        $online->fill(['status' => OnlinePaymentStatus::Captured, 'captured_at' => $this->clock->now()]);
    }

    private function smaller(Money $a, Money $b): Money
    {
        return $a->isLessThan($b) ? $a : $b;
    }

    /** @return Builder<OnlinePayment> */
    private function query(): Builder
    {
        return OwnershipScope::apply(OnlinePayment::query()->with(self::RELATIONS), 'online_payments', Auth::user());
    }
}
