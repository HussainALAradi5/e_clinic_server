<?php

declare(strict_types=1);

namespace App\Http\Resources\Payments;

use App\Enums\Shared\PaymentMethod;
use App\Http\Resources\Shared\Summary;
use App\Models\Payments\InsuranceClaim;
use App\Models\Payments\Invoice;
use App\Utilities\Shared\ClinicClock;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Patient invoice with its totals; lines, payments and refunds on the detail.
 *
 * @mixin Invoice
 */
final class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'invoice_number' => $this->invoice_number,
            'patient_id' => $this->patient_id,
            'patient' => $this->whenLoaded('patient', fn (): array => [
                ...Summary::patient($this->patient),
                'insurer' => $this->patient->relationLoaded('insurer') && $this->patient->insurer !== null
                    ? ['id' => $this->patient->insurer->id, 'name' => $this->patient->insurer->name]
                    : null,
            ]),
            'issue_date' => $this->issue_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'currency' => $this->currency,
            'subtotal' => MoneyHelper::toApi($this->subtotal),
            'discount_total' => MoneyHelper::toApi($this->discount_total),
            'tax_total' => MoneyHelper::toApi($this->tax_total),
            'total' => MoneyHelper::toApi($this->total),
            'paid_amount' => MoneyHelper::toApi($this->paid_amount),
            'refunded_amount' => MoneyHelper::toApi($this->refunded_amount),
            'balance_due' => MoneyHelper::toApi($this->balance_due),
            'status' => $this->status->value,
            'payment_state' => $this->paymentState(app(ClinicClock::class)->today())->value,
            'payment_methods' => $this->whenLoaded('payments', fn (): array => array_map(fn (PaymentMethod $m): string => $m->value, $this->paymentMethods())),
            'notes' => $this->notes,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'issued_by' => $this->whenLoaded('issuer', fn (): ?array => Summary::user($this->issuer)),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'journal_entry_id' => $this->journal_entry_id,
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'refunds' => RefundResource::collection($this->whenLoaded('refunds')),
            'claims' => $this->whenLoaded('claims', fn (): array => $this->claims->map(fn (InsuranceClaim $c): array => [
                'id' => $c->id,
                'code' => $c->code,
                'status' => $c->status->value,
                'claimed_amount' => MoneyHelper::toApi($c->claimed_amount),
            ])->all()),
            'is_archived' => $this->is_archived,
            'is_deleted' => $this->is_deleted,
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
