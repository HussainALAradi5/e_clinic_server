<?php

declare(strict_types=1);

namespace App\Http\Resources\Payments;

use App\Models\Payments\InvoiceItem;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Invoice line; `source` names the visit / order it bills (null = typed by hand).
 *
 * @mixin InvoiceItem
 */
final class InvoiceItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'billable_service_id' => $this->billable_service_id,
            'billable_service' => $this->whenLoaded('billableService', fn (): ?array => $this->billableService === null ? null : [
                'id' => $this->billableService->id,
                'code' => $this->billableService->code,
                'name' => $this->billableService->name,
            ]),
            'source' => $this->chargeable_type === null ? null : [
                'type' => $this->chargeable_type,
                'id' => $this->chargeable_id,
                'code' => $this->relationLoaded('chargeable') ? $this->chargeable?->getAttribute('code') : null,
            ],
            'doctor_id' => $this->doctor_id,
            'doctor' => $this->whenLoaded('doctor', fn (): ?array => $this->doctor === null ? null : ['id' => $this->doctor->id, 'full_name' => $this->doctor->person->full_name]),
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => MoneyHelper::toApi($this->unit_price),
            'discount_type' => $this->discount_type?->value,
            'discount_value' => $this->discount_value,
            'discount_amount' => MoneyHelper::toApi($this->discount_amount),
            'tax_rate' => $this->tax_rate,
            'tax_amount' => MoneyHelper::toApi($this->tax_amount),
            'line_total' => MoneyHelper::toApi($this->line_total),
        ];
    }
}
