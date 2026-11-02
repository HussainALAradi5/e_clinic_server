<?php

declare(strict_types=1);

namespace App\Http\Resources\Payments;

use App\Http\Resources\Shared\Summary;
use App\Models\Payments\Payment;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payment (its code is the receipt number) with what is still refundable.
 *
 * @mixin Payment
 */
final class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'invoice_id' => $this->invoice_id,
            'invoice' => $this->whenLoaded('invoice', fn (): array => [
                'id' => $this->invoice->id,
                'code' => $this->invoice->code,
                'invoice_number' => $this->invoice->invoice_number,
                'total' => MoneyHelper::toApi($this->invoice->total),
                'balance_due' => MoneyHelper::toApi($this->invoice->balance_due),
            ]),
            'patient_id' => $this->patient_id,
            'patient' => $this->whenLoaded('patient', fn (): array => Summary::patient($this->patient)),
            'amount' => MoneyHelper::toApi($this->amount),
            'refunded_amount' => MoneyHelper::toApi($this->refunded_amount),
            'refundable' => MoneyHelper::toApi($this->refundable()),
            'method' => $this->method->value,
            'status' => $this->status->value,
            'reference_number' => $this->reference_number,
            'card_last4' => $this->card_last4,
            'paid_at' => $this->paid_at->toIso8601String(),
            'received_by' => $this->whenLoaded('receiver', fn (): ?array => Summary::user($this->receiver)),
            'cash_session_id' => $this->cash_session_id,
            'cash_session' => $this->whenLoaded('cashSession', fn (): ?array => $this->cashSession === null ? null : [
                'id' => $this->cashSession->id,
                'code' => $this->cashSession->code,
                'status' => $this->cashSession->status->value,
            ]),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
            'notes' => $this->notes,
            'refunds' => RefundResource::collection($this->whenLoaded('refunds')),
            'journal_entry_id' => $this->journal_entry_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
