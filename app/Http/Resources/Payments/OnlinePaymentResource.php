<?php

declare(strict_types=1);

namespace App\Http\Resources\Payments;

use App\Models\Payments\OnlinePayment;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An online payment attempt: its status, where to pay while it waits, and the booked payment once captured.
 * The gateway's charge id is shown to staff only.
 *
 * @mixin OnlinePayment
 */
final class OnlinePaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $waiting = ! $this->status->isFinal();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status->value,
            'amount' => MoneyHelper::toApi($this->amount),
            'invoice' => ['id' => $this->invoice_id, 'number' => $this->invoice->invoice_number ?? $this->invoice->code],
            'patient' => ['id' => $this->patient_id, 'full_name' => $this->patient->person->full_name],
            'checkout_url' => $waiting ? $this->checkout_url : null,
            'payment_id' => $this->payment_id,
            'provider' => $this->provider,
            'charge_id' => $this->when($request->user()?->can('online_payments.read_all') ?? false, $this->charge_id),
            'failure_reason' => $this->failure_reason,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'captured_at' => $this->captured_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
