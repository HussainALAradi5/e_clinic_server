<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use App\Events\Appointments\AppointmentCancelled;
use App\Events\Appointments\AppointmentCompleted;
use App\Events\Appointments\AppointmentNoShow;
use App\Events\Appointments\ClinicalOrderCompleted;
use App\Events\Documents\WrittenDocumentIssued;
use App\Events\Payments\PaymentVoided;
use App\Events\Payments\RefundApproved;
use App\Events\Payments\RefundRejected;
use App\Events\Payments\RefundRequested;
use App\Http\Resources\Payments\AppointmentFeeResource;
use App\Http\Resources\Payments\BillableServiceResource;
use App\Http\Resources\Payments\InsuranceClaimResource;
use App\Http\Resources\Payments\InvoiceResource;
use App\Http\Resources\Payments\PaymentResource;
use App\Http\Resources\Payments\RefundResource;
use App\Imports\Payments\BillableServiceImport;
use App\Interfaces\Payments\AppointmentFeeServiceInterface;
use App\Interfaces\Payments\BillableServiceServiceInterface;
use App\Interfaces\Payments\InsuranceClaimServiceInterface;
use App\Interfaces\Payments\InvoiceServiceInterface;
use App\Interfaces\Payments\OnlinePaymentServiceInterface;
use App\Interfaces\Payments\PaymentGatewayInterface;
use App\Interfaces\Payments\PaymentServiceInterface;
use App\Interfaces\Payments\RefundServiceInterface;
use App\Interfaces\Payments\WalletServiceInterface;
use App\Listeners\Payments\ChargeAppointmentFee;
use App\Listeners\Payments\ChargeCompletedWork;
use App\Listeners\Payments\ChargeDocumentFee;
use App\Listeners\Payments\ReturnVoidedBalancePayment;
use App\Listeners\Payments\SendRefundNotifications;
use App\Models\Payments\AppointmentFee;
use App\Models\Payments\BillableService;
use App\Models\Payments\InsuranceClaim;
use App\Models\Payments\Invoice;
use App\Models\Payments\Payment;
use App\Models\Payments\Refund;
use App\Services\Bulk\BulkActionRegistry;
use App\Services\Payments\AppointmentFeeService;
use App\Services\Payments\BillableServiceService;
use App\Services\Payments\Gateways\FakeGateway;
use App\Services\Payments\Gateways\TapGateway;
use App\Services\Payments\InsuranceClaimService;
use App\Services\Payments\InvoiceService;
use App\Services\Payments\OnlinePaymentService;
use App\Services\Payments\PaymentService;
use App\Services\Payments\RefundService;
use App\Services\Payments\WalletService;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Payments & Billing module: the service catalog, patient invoices (the single billing path), payments and refunds.
 * Completed visits, sessions and orders are charged onto the patient's open draft automatically.
 */
final class PaymentsServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        BillableServiceServiceInterface::class => BillableServiceService::class,
        InvoiceServiceInterface::class => InvoiceService::class,
        PaymentServiceInterface::class => PaymentService::class,
        RefundServiceInterface::class => RefundService::class,
        InsuranceClaimServiceInterface::class => InsuranceClaimService::class,
        WalletServiceInterface::class => WalletService::class,
        AppointmentFeeServiceInterface::class => AppointmentFeeService::class,
        OnlinePaymentServiceInterface::class => OnlinePaymentService::class,
    ];

    public function register(): void
    {
        // The gateway: Tap with real (or sk_test_) keys; the local fake checkout only outside production.
        $this->app->singleton(PaymentGatewayInterface::class, function (): PaymentGatewayInterface {
            if (Config::string('payments.online.driver') === 'tap') {
                return new TapGateway;
            }
            if ($this->app->isProduction()) {
                throw new RuntimeException('PAYMENT_GATEWAY=fake is not allowed in production.');
            }

            return new FakeGateway;
        });
    }

    public function boot(BulkActionRegistry $bulk): void
    {
        Event::listen([AppointmentCompleted::class, ClinicalOrderCompleted::class], ChargeCompletedWork::class);
        Event::listen([RefundRequested::class, RefundApproved::class, RefundRejected::class], SendRefundNotifications::class);
        Event::listen(PaymentVoided::class, ReturnVoidedBalancePayment::class);
        Event::listen(WrittenDocumentIssued::class, ChargeDocumentFee::class);
        Event::listen([AppointmentNoShow::class, AppointmentCancelled::class], ChargeAppointmentFee::class);

        $bulk->register('services', BillableService::class, BillableService::listQueryConfig(), BillableServiceService::class)
            ->exportable(BillableServiceResource::class, fn (Builder $q) => $q->with(['specialty', 'revenueAccount']), values: [
                'price' => fn (Model $s): string => $s instanceof BillableService ? MoneyHelper::format($s->price) : '',
                'specialty' => fn (Model $s): string => $s instanceof BillableService ? (string) $s->specialty?->name : '',
            ])
            ->importable(BillableServiceImport::class);

        $patient = fn (Model $m): string => $m->getRelationValue('patient')?->person->full_name ?? '';
        $money = fn (string $field): \Closure => fn (Model $m): string => MoneyHelper::format($m->getAttribute($field));

        // Finance lists share the `transactions.export` right; drafts are issued one by one (each posts revenue).
        $bulk->register('invoices', Invoice::class, Invoice::listQueryConfig(), genericActions: false)
            ->exportable(InvoiceResource::class, fn (Builder $q) => $q->with('patient.person'), values: [
                'patient' => $patient, 'total' => $money('total'), 'balance_due' => $money('balance_due'),
            ])
            ->permissionsOf('transactions');

        $bulk->register('payments', Payment::class, Payment::listQueryConfig(), genericActions: false)
            ->exportable(PaymentResource::class, fn (Builder $q) => $q->with(PaymentService::RELATIONS), values: [
                'patient' => $patient,
                'amount' => $money('amount'),
                'invoice' => fn (Model $p): string => $p instanceof Payment ? (string) ($p->invoice->invoice_number ?? $p->invoice->code) : '',
                'received_by' => fn (Model $p): string => $p instanceof Payment ? $p->receiver->person->full_name : '',
            ])
            ->permissionsOf('transactions');

        $bulk->register('refunds', Refund::class, Refund::listQueryConfig(), genericActions: false)
            ->action('approve', 'refunds.write&refunds.approve', [RefundServiceInterface::class, 'approve'])
            ->exportable(RefundResource::class, fn (Builder $q) => $q->with(RefundService::RELATIONS), values: [
                'patient' => fn (Model $r): string => $r instanceof Refund ? $r->invoice->patient->person->full_name : '',
                'amount' => $money('amount'),
            ])
            ->permissionsOf('transactions');

        // Fees: listed and exported; waived one by one (each needs its own reason).
        $bulk->register('appointment_fees', AppointmentFee::class, AppointmentFee::listQueryConfig(), genericActions: false)
            ->exportable(AppointmentFeeResource::class, fn (Builder $q) => $q->with(AppointmentFeeService::RELATIONS), values: [
                'patient' => fn (Model $f): string => $f instanceof AppointmentFee ? $f->patient->person->full_name : '',
                'amount' => $money('amount'),
            ])
            ->permissionsOf('transactions');

        $bulk->register('claims', InsuranceClaim::class, InsuranceClaim::listQueryConfig(), genericActions: false)
            ->action('submit', 'claims.write', [InsuranceClaimServiceInterface::class, 'submit'])
            ->exportable(InsuranceClaimResource::class, fn (Builder $q) => $q->with(InsuranceClaimService::RELATIONS), values: [
                'patient' => $patient,
                'insurer' => fn (Model $c): string => $c instanceof InsuranceClaim ? $c->insurer->name : '',
                'invoice' => fn (Model $c): string => $c instanceof InsuranceClaim ? (string) ($c->invoice->invoice_number ?? $c->invoice->code) : '',
                'claimed_amount' => $money('claimed_amount'),
            ])
            ->permissionsOf('transactions');
    }
}
