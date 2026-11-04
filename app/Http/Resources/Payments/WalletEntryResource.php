<?php

declare(strict_types=1);

namespace App\Http\Resources\Payments;

use App\Models\Payments\PatientWalletEntry;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One movement of a patient's balance, with the balance after it.
 *
 * @mixin PatientWalletEntry
 */
final class WalletEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type->value,
            'is_credit' => $this->type->isCredit(),
            'amount' => MoneyHelper::toApi($this->amount),
            'balance_after' => MoneyHelper::toApi($this->balance_after),
            'method' => $this->method?->value,
            'reference_number' => $this->reference_number,
            'invoice_id' => $this->invoice_id,
            'payment_id' => $this->payment_id,
            'reason' => $this->reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
