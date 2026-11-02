<?php

declare(strict_types=1);

namespace App\Http\Resources\Pharmacy;

use App\Http\Resources\Shared\Summary;
use App\Models\HR\Employee;
use App\Models\Pharmacy\Dispensation;
use App\Models\Pharmacy\DispensationItem;
use App\Utilities\Pharmacy\Qty;
use App\Utilities\Shared\MoneyHelper;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dispensation
 */
final class DispensationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $staff = fn (?Employee $employee): ?array => $employee === null ? null : ['id' => $employee->id, 'full_name' => $employee->person->full_name];

        return [
            'id' => $this->id,
            'code' => $this->code,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'prescription_id' => $this->prescription_id,
            'prescription' => $this->whenLoaded('prescription', fn (): ?array => $this->prescription === null ? null : ['id' => $this->prescription->id, 'code' => $this->prescription->code]),
            'appointment_id' => $this->appointment_id,
            'appointment' => $this->whenLoaded('appointment', fn (): ?array => $this->appointment === null ? null : ['id' => $this->appointment->id, 'code' => $this->appointment->code]),
            'patient_id' => $this->patient_id,
            'patient' => $this->whenLoaded('patient', fn (): array => Summary::patient($this->patient)),
            'dispensed_by' => $this->whenLoaded('dispenser', fn (): ?array => $staff($this->dispenser)),
            'witness' => $this->whenLoaded('witness', fn (): ?array => $staff($this->witness)),
            'dispensed_at' => $this->dispensed_at->toIso8601String(),
            'notes' => $this->notes,
            'cancellation_reason' => $this->cancellation_reason,
            'journal_entry_id' => $this->journal_entry_id,
            'reversal_journal_entry_id' => $this->reversal_journal_entry_id,
            'invoice' => $this->whenLoaded('invoiceLine', fn (): ?array => $this->invoiceSummary()),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (DispensationItem $item): array => [
                'id' => $item->id,
                'prescription_item_id' => $item->prescription_item_id,
                'medication_id' => $item->medication_id,
                'medication' => ['id' => $item->medication->id, 'code' => $item->medication->code, 'name' => $item->medication->displayName(), 'unit' => $item->medication->unit->value],
                'batch' => $item->relationLoaded('batch') ? ['id' => $item->batch->id, 'code' => $item->batch->code, 'batch_number' => $item->batch->batch_number, 'expiry_date' => $item->batch->expiry_date->toDateString()] : null,
                'quantity' => Qty::str($item->quantity),
                'unit_price' => MoneyHelper::toApi($item->unit_price),
                'line_total' => MoneyHelper::toApi($item->unit_price->multipliedBy(Qty::of($item->quantity), RoundingMode::HALF_UP)),
                'route' => $item->route?->value,
                'dose' => $item->dose,
            ])->all()),
            'is_archived' => $this->is_archived,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
