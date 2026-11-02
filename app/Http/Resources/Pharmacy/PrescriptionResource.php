<?php

declare(strict_types=1);

namespace App\Http\Resources\Pharmacy;

use App\Http\Resources\Shared\Summary;
use App\Models\Pharmacy\Prescription;
use App\Models\Pharmacy\PrescriptionItem;
use App\Utilities\Pharmacy\Qty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Prescription
 */
final class PrescriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'patient_id' => $this->patient_id,
            'patient' => $this->whenLoaded('patient', fn (): array => Summary::patient($this->patient)),
            'doctor_id' => $this->doctor_id,
            'doctor' => $this->whenLoaded('doctor', fn (): array => ['id' => $this->doctor->id, 'full_name' => $this->doctor->person->full_name]),
            'appointment_id' => $this->appointment_id,
            'appointment' => $this->whenLoaded('appointment', fn (): ?array => $this->appointment === null ? null : ['id' => $this->appointment->id, 'code' => $this->appointment->code, 'scheduled_date' => $this->appointment->scheduled_date->toDateString()]),
            'treatment_id' => $this->treatment_id,
            'treatment' => $this->whenLoaded('treatment', fn (): ?array => $this->treatment === null ? null : ['id' => $this->treatment->id, 'code' => $this->treatment->code]),
            'status' => $this->status->value,
            'valid_until' => $this->valid_until->toDateString(),
            'notes' => $this->notes,
            'cancellation_reason' => $this->cancellation_reason,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (PrescriptionItem $item): array => [
                'id' => $item->id,
                'medication_id' => $item->medication_id,
                'medication' => [
                    'id' => $item->medication->id,
                    'code' => $item->medication->code,
                    'name' => $item->medication->displayName(),
                    'unit' => $item->medication->unit->value,
                    'gtin' => $item->medication->gtin,
                    'is_controlled' => $item->medication->is_controlled,
                ],
                'dose' => $item->dose,
                'frequency' => $item->frequency,
                'route' => $item->route->value,
                'duration_days' => $item->duration_days,
                'quantity' => Qty::str($item->quantity),
                'dispensed_quantity' => $item->dispensed_quantity === null ? null : Qty::str($item->dispensed_quantity),
                'remaining_quantity' => $item->dispensed_quantity === null ? null : (string) $item->remaining(),
                'instructions' => $item->instructions,
            ])->all()),
            'dispensations' => DispensationResource::collection($this->whenLoaded('dispensations')),
            'is_archived' => $this->is_archived,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
