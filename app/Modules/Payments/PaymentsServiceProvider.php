<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use App\Modules\Appointments\Appointments\Events\AppointmentCompleted;
use App\Modules\Appointments\Orders\Events\ClinicalOrderCompleted;
use App\Modules\Payments\Catalog\Contracts\BillableServiceServiceInterface;
use App\Modules\Payments\Catalog\Http\Resources\BillableServiceResource;
use App\Modules\Payments\Catalog\Imports\BillableServiceImport;
use App\Modules\Payments\Catalog\Models\BillableService;
use App\Modules\Payments\Catalog\Services\BillableServiceService;
use App\Modules\Payments\Claims\Contracts\InsuranceClaimServiceInterface;
use App\Modules\Payments\Claims\Http\Resources\InsuranceClaimResource;
use App\Modules\Payments\Claims\Models\InsuranceClaim;
use App\Modules\Payments\Claims\Services\InsuranceClaimService;
use App\Modules\Payments\Invoices\Contracts\InvoiceServiceInterface;
use App\Modules\Payments\Invoices\Http\Resources\InvoiceResource;
use App\Modules\Payments\Invoices\Listeners\ChargeCompletedWork;
use App\Modules\Payments\Invoices\Models\Invoice;
use App\Modules\Payments\Invoices\Services\InvoiceService;
use App\Modules\Payments\Payments\Contracts\PaymentServiceInterface;
use App\Modules\Payments\Payments\Http\Resources\PaymentResource;
use App\Modules\Payments\Payments\Models\Payment;
use App\Modules\Payments\Payments\Services\PaymentService;
use App\Modules\Payments\Refunds\Contracts\RefundServiceInterface;
use App\Modules\Payments\Refunds\Events\RefundApproved;
use App\Modules\Payments\Refunds\Events\RefundRejected;
use App\Modules\Payments\Refunds\Events\RefundRequested;
use App\Modules\Payments\Refunds\Http\Resources\RefundResource;
use App\Modules\Payments\Refunds\Listeners\SendRefundNotifications;
use App\Modules\Payments\Refunds\Models\Refund;
use App\Modules\Payments\Refunds\Services\RefundService;
use App\Shared\Bulk\BulkActionRegistry;
use App\Shared\Support\MoneyHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

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
    ];

    public function boot(BulkActionRegistry $bulk): void
    {
        Event::listen([AppointmentCompleted::class, ClinicalOrderCompleted::class], ChargeCompletedWork::class);
        Event::listen([RefundRequested::class, RefundApproved::class, RefundRejected::class], SendRefundNotifications::class);

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
