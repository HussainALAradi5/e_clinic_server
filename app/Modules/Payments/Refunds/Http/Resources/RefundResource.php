<?php

declare(strict_types=1);

namespace App\Modules\Payments\Refunds\Http\Resources;

use App\Modules\Payments\Refunds\Models\Refund;
use App\Shared\Http\Resources\Summary;
use App\Shared\Support\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Refund request and where it stands: pending → approved / rejected → paid out.
 *
 * @mixin Refund
 */
final class RefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'payment_id' => $this->payment_id,
            'payment' => $this->whenLoaded('payment', fn (): array => [
                'id' => $this->payment->id,
                'code' => $this->payment->code,
                'method' => $this->payment->method->value,
                'amount' => MoneyHelper::toApi($this->payment->amount),
                'paid_at' => $this->payment->paid_at->toIso8601String(),
            ]),
            'invoice_id' => $this->invoice_id,
            'invoice' => $this->whenLoaded('invoice', fn (): array => [
                'id' => $this->invoice->id,
                'code' => $this->invoice->code,
                'invoice_number' => $this->invoice->invoice_number,
            ]),
            'patient' => $this->when(
                $this->relationLoaded('invoice') && $this->invoice->relationLoaded('patient'),
                fn (): array => Summary::patient($this->invoice->patient),
            ),
            'amount' => MoneyHelper::toApi($this->amount),
            'method' => $this->method->value,
            'reason' => $this->reason,
            'status' => $this->status->value,
            'is_processed' => $this->isProcessed(),
            'requested_by' => $this->whenLoaded('requester', fn (): ?array => Summary::user($this->requester)),
            'approved_by' => $this->whenLoaded('approver', fn (): ?array => Summary::user($this->approver)),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'processed_by' => $this->whenLoaded('processor', fn (): ?array => Summary::user($this->processor)),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'reference_number' => $this->reference_number,
            'cash_session_id' => $this->cash_session_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
